<?php

namespace App\Services;

use App\Jobs\DispatchWhatsappFlowSubmissionWebhook;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowResponse;
use Modules\Flowmaker\Models\Contact;

class WhatsappFlowSubmissionService
{
    public function __construct(
        private WhatsappFlowResponseService $responseService,
        private WhatsappFlowVariableMapper $variableMapper,
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
        ?int $automationFlowId = null,
        ?string $variablePrefix = null,
        array $customMappings = [],
        ?bool $keepLegacyVariables = null,
    ): void {
        $cleanData = $this->responseService->cleanResponses($responseData);

        if ($flowResponse->status !== 'completed' || ! empty($cleanData)) {
            $flowResponse->markCompleted($cleanData);
        }

        $whatsappFlow = $flowResponse->whatsappFlow ?? WhatsappFlow::find($flowResponse->whatsapp_flow_id);

        if ($contact && $automationFlowId) {
            $prefix = $variablePrefix ?: ($flowResponse->variable_prefix ?: 'form');
            $keepLegacy = $keepLegacyVariables ?? (bool) config('whatsapp-flows.keep_legacy_variables', true);
            $this->syncResponseToContactState(
                $contact,
                $automationFlowId,
                $cleanData,
                $whatsappFlow,
                $prefix,
                $customMappings,
                $keepLegacy
            );
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
        ?WhatsappFlow $whatsappFlow = null,
        ?string $variablePrefix = null,
        array $customMappings = [],
        ?bool $keepLegacyVariables = null,
    ): void {
        $prefix = $variablePrefix ?: 'form';
        $keepLegacy = $keepLegacyVariables ?? (bool) config('whatsapp-flows.keep_legacy_variables', true);

        $this->variableMapper->syncToContactState(
            $contact,
            $automationFlowId,
            $responseData,
            $whatsappFlow,
            $prefix,
            $customMappings,
            $keepLegacy
        );
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
}
