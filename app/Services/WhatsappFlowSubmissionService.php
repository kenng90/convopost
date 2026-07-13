<?php

namespace App\Services;

use App\Jobs\DispatchWhatsappFlowSubmissionWebhook;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowResponse;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class WhatsappFlowSubmissionService
{
    public function __construct(
        private WhatsappFlowResponseService $responseService
    ) {
    }

    /**
     * Handle a completed form submission: persist, flatten variables, dispatch webhooks.
     *
     * @param  array<string, mixed>  $responseData
     */
    public function handleCompleted(
        WhatsappFlowResponse $flowResponse,
        array $responseData,
        ?Contact $contact = null,
        ?int $automationFlowId = null
    ): void {
        $cleanData = $this->responseService->cleanResponses($responseData);

        if ($flowResponse->status !== 'completed' || ! empty($cleanData)) {
            $flowResponse->markCompleted($cleanData);
        }

        $whatsappFlow = $flowResponse->whatsappFlow ?? WhatsappFlow::find($flowResponse->whatsapp_flow_id);

        if ($contact && $automationFlowId) {
            $this->syncResponseToContactState($contact, $automationFlowId, $cleanData, $whatsappFlow);
            \App\Services\Flowmaker\FlowRunLogger::log(
                (int) $automationFlowId,
                (int) $contact->id,
                'whatsapp_form_completed',
                $flowResponse->flow_node_id ? (string) $flowResponse->flow_node_id : null,
                (string) $flowResponse->whatsapp_flow_id
            );
        } elseif ($flowResponse->flow_id) {
            \App\Services\Flowmaker\FlowRunLogger::log(
                (int) $flowResponse->flow_id,
                $flowResponse->contact_id ? (int) $flowResponse->contact_id : null,
                'whatsapp_form_completed',
                $flowResponse->flow_node_id ? (string) $flowResponse->flow_node_id : null,
                (string) $flowResponse->whatsapp_flow_id
            );
        }

        if ($whatsappFlow && $whatsappFlow->webhook_enabled && ! empty($whatsappFlow->webhook_url) && ! $flowResponse->webhook_dispatched_at) {
            DispatchWhatsappFlowSubmissionWebhook::dispatch(
                $flowResponse->fresh(['whatsappFlow']),
                $cleanData
            );
            $flowResponse->update(['webhook_dispatched_at' => now()]);
        }
    }

    /**
     * Flatten form field values into individual contact state variables for downstream nodes.
     *
     * @param  array<string, mixed>  $responseData
     */
    public function syncResponseToContactState(
        Contact $contact,
        int $automationFlowId,
        array $responseData,
        ?WhatsappFlow $whatsappFlow = null
    ): void {
        $contact->setContactState($automationFlowId, 'whatsapp_flow_responses', json_encode($responseData));

        $definitions = $this->responseService->getInputFieldDefinitions($whatsappFlow);
        $definitionMap = collect($definitions)->keyBy('key');

        foreach ($responseData as $fieldKey => $value) {
            $contact->setContactState($automationFlowId, 'form_'.$fieldKey, is_array($value) ? json_encode($value) : (string) $value);

            $definition = $definitionMap->get($fieldKey);
            if ($definition && ! empty($definition['label'])) {
                $slugKey = $this->slugifyFieldLabel($definition['label']);
                if ($slugKey !== '') {
                    $contact->setContactState($automationFlowId, 'form_'.$slugKey, is_array($value) ? json_encode($value) : (string) $value);
                }
            }
        }

        Log::info('WhatsApp Form: flattened field variables to contact state', [
            'contact_id' => $contact->id,
            'flow_id' => $automationFlowId,
            'field_count' => count($responseData),
        ]);
    }

    /**
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public function getFieldOptionsForForm(WhatsappFlow $flow): array
    {
        return array_map(fn (array $def) => [
            'key' => $def['key'],
            'label' => $def['label'],
            'type' => $def['type'],
            'screen_title' => $def['screen_title'],
        ], $this->responseService->getInputFieldDefinitions($flow));
    }

    private function slugifyFieldLabel(string $label): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $label) ?? '', '_'));

        return trim($slug, '_');
    }
}
