<?php

namespace App\Services;

use App\Models\Company;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Wpbox\Models\Message;

class WhatsappFlowSendService
{
    /**
     * Send a published WhatsApp Form to a contact.
     *
     * @return array{success: bool, message?: string, flow_response_id?: int}
     */
    public function sendToContact(
        WhatsappFlow $whatsappFlow,
        Contact $contact,
        ?int $automationFlowId = null,
        ?string $flowNodeId = null,
        ?string $header = null,
        ?string $footer = null
    ): array {
        if (empty($whatsappFlow->meta_flow_id)) {
            return [
                'success' => false,
                'message' => 'Form must be published to Meta before it can be sent.',
            ];
        }

        $company = Company::find($contact->company_id);
        if (! $company) {
            return ['success' => false, 'message' => 'Company not found.'];
        }

        $accessToken = $company->getConfig('whatsapp_permanent_access_token', '');
        $phoneId = $company->getConfig('whatsapp_phone_number_id', '');

        if (empty($accessToken) || empty($phoneId)) {
            return ['success' => false, 'message' => 'WhatsApp API credentials are not configured.'];
        }

        $headerText = $header ?? 'Complete the form';
        $footerText = $footer ?? 'Your responses help us serve you better';

        if ($automationFlowId) {
            $headerText = $contact->changeVariables($headerText, $automationFlowId);
            $footerText = $contact->changeVariables($footerText, $automationFlowId);
        }

        try {
            $flowResponse = WhatsappFlowResponse::create([
                'company_id' => $contact->company_id,
                'whatsapp_flow_id' => $whatsappFlow->id,
                'flow_id' => $automationFlowId,
                'flow_node_id' => $flowNodeId,
                'contact_id' => $contact->id,
                'contact_phone' => $contact->phone,
                'contact_name' => $contact->name,
                'status' => 'pending',
                'sent_at' => now(),
            ]);

            $flowToken = 'flow_'.$flowResponse->id.'_'.time();
            $flowResponse->update(['flow_token' => $flowToken]);

            if ($automationFlowId) {
                $contact->setContactState($automationFlowId, 'whatsapp_flow_id', $whatsappFlow->id);
                $contact->setContactState($automationFlowId, 'whatsapp_flow_response_id', $flowResponse->id);
                $contact->setContactState($automationFlowId, 'flow_token', $flowToken);
                if ($flowNodeId) {
                    $contact->setContactState($automationFlowId, 'current_node', $flowNodeId);
                }
            }

            $flowAction = $this->resolveFlowAction($whatsappFlow);
            $prefill = app(\App\Services\WhatsappFlows\WhatsappFlowCommercePrefillService::class)
                ->buildPrefillData($whatsappFlow, $contact, $automationFlowId);

            if ($automationFlowId && $prefill !== []) {
                $contact->setContactState($automationFlowId, 'whatsapp_flow_prefill', json_encode($prefill));
            }

            $parameters = [
                'flow_message_version' => '3',
                'flow_token' => $flowToken,
                'flow_id' => $whatsappFlow->meta_flow_id,
                'flow_cta' => 'Open Form',
                'flow_action' => $flowAction,
            ];

            // navigate requires an explicit first screen; data_exchange calls INIT on the endpoint.
            if ($flowAction === 'navigate') {
                $parameters['flow_action_payload'] = [
                    'screen' => $this->resolveFirstScreenId($whatsappFlow),
                    'data' => (object) $prefill,
                ];
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $contact->phone,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'flow',
                    'body' => ['text' => $headerText],
                    'footer' => ['text' => $footerText],
                    'action' => [
                        'name' => 'flow',
                        'parameters' => $parameters,
                    ],
                ],
            ];

            Log::info('WhatsApp Form send payload', [
                'whatsapp_flow_id' => $whatsappFlow->id,
                'flow_action' => $flowAction,
                'meta_flow_id' => $whatsappFlow->meta_flow_id,
            ]);

            $url = 'https://graph.facebook.com/v19.0/'.$phoneId.'/messages';
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            $responseBody = $response->json();

            if (! $response->successful()) {
                Log::error('WhatsApp Form send failed', ['error' => $responseBody]);

                return [
                    'success' => false,
                    'message' => $responseBody['error']['message'] ?? 'Failed to send form via WhatsApp API.',
                ];
            }

            $fbMessageId = $responseBody['messages'][0]['id'] ?? null;
            Message::create([
                'contact_id' => $contact->id,
                'company_id' => $contact->company_id,
                'value' => $headerText,
                'is_message_by_contact' => false,
                'is_campign_messages' => false,
                'status' => 1,
                'fb_message_id' => $fbMessageId,
            ]);

            if ($automationFlowId) {
                \App\Services\Flowmaker\FlowRunLogger::log(
                    (int) $automationFlowId,
                    (int) $contact->id,
                    'whatsapp_form_sent',
                    $flowNodeId,
                    (string) $whatsappFlow->id
                );
            }

            return [
                'success' => true,
                'message' => 'Form sent successfully.',
                'flow_response_id' => $flowResponse->id,
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Form send exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send a test form to a phone number (creates or finds contact).
     *
     * @return array{success: bool, message?: string}
     */
    public function sendTest(WhatsappFlow $whatsappFlow, string $phone, int $companyId): array
    {
        $contact = Contact::firstOrCreate(
            ['phone' => $phone, 'company_id' => $companyId],
            ['name' => 'Test Contact', 'user_id' => null]
        );

        return $this->sendToContact(
            $whatsappFlow,
            $contact,
            null,
            null,
            'Test: '.$whatsappFlow->name,
            'This is a test send from your form builder.'
        );
    }

    /**
     * Endpoint-powered forms (live booking catalog/slots) must open with data_exchange
     * so Meta calls INIT and the dropdown options are populated.
     */
    public function resolveFlowAction(WhatsappFlow $whatsappFlow): string
    {
        return $this->formNeedsEndpointOnOpen($whatsappFlow) ? 'data_exchange' : 'navigate';
    }

    public function formNeedsEndpointOnOpen(WhatsappFlow $whatsappFlow): bool
    {
        $json = is_array($whatsappFlow->flow_json) ? $whatsappFlow->flow_json : [];
        $firstScreen = $json['screens'][0] ?? null;

        if (! is_array($firstScreen)) {
            return (bool) ($whatsappFlow->requires_endpoint ?? false);
        }

        if (! empty($firstScreen['endpoint_template'])) {
            return true;
        }

        if (! empty($firstScreen['dynamic_data'])) {
            return true;
        }

        foreach ($firstScreen['fields'] ?? [] as $field) {
            if (! is_array($field)) {
                continue;
            }

            if (! empty($field['dynamic_data_source']) || ! empty($field['data_source_key'])) {
                return true;
            }
        }

        return (bool) ($whatsappFlow->requires_endpoint ?? false)
            || filled($json['data_api_version'] ?? null);
    }

    private function resolveFirstScreenId(WhatsappFlow $whatsappFlow): string
    {
        $json = is_array($whatsappFlow->flow_json) ? $whatsappFlow->flow_json : [];
        $firstScreen = $json['screens'][0] ?? null;
        $rawId = is_array($firstScreen) ? ($firstScreen['id'] ?? '') : '';

        return filled($rawId) ? (string) $rawId : 'WELCOME';
    }
}
