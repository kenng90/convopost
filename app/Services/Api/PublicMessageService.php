<?php

namespace App\Services\Api;

use App\Models\Company;
use App\Services\Telephony\Sms\SmsSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Wpbox\Http\Controllers\APIController;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Traits\Contacts;

class PublicMessageService
{
    use Contacts;

    public function __construct(
        private readonly SmsSender $smsSender,
        private readonly PublicWebhookDispatcher $webhooks,
    ) {
    }

    public function send(Request $request, Company $company): JsonResponse
    {
        $channel = strtolower((string) $request->input('channel', 'whatsapp'));

        if ($channel === 'sms') {
            return $this->sendSms($request, $company);
        }

        return $this->sendWhatsApp($request, $company);
    }

    private function sendSms(Request $request, Company $company): JsonResponse
    {
        $phone = (string) ($request->input('to') ?: $request->input('phone'));
        $body = (string) ($request->input('body') ?: $request->input('message'));

        if ($phone === '' || $body === '') {
            return PublicApiResponse::error('invalid_request', 'to/phone and body/message are required for SMS.', 422);
        }

        $contact = $this->getOrMakeContact($phone, $company, $request->input('name', $phone));
        $result = $this->smsSender->send($company, $phone, $body);

        if (! $result->success) {
            $this->webhooks->dispatch($company->id, 'message.failed', [
                'channel' => 'sms',
                'contact_id' => $contact->id,
                'phone' => $phone,
                'error' => $result->message,
            ]);

            return PublicApiResponse::error('send_failed', $result->message, 422);
        }

        $message = Message::create([
            'contact_id' => $contact->id,
            'company_id' => $company->id,
            'value' => $body,
            'is_message_by_contact' => false,
            'is_campign_messages' => false,
            'status' => Message::STATUS_SENT,
            'fb_message_id' => $result->providerMessageId,
            'channel' => 'sms',
            'buttons' => '[]',
            'components' => '',
        ]);

        return PublicApiResponse::success([
            'id' => $message->id,
            'channel' => 'sms',
            'status' => 'sent',
            'provider_message_id' => $result->providerMessageId,
            'contact_id' => $contact->id,
        ], 201);
    }

    private function sendWhatsApp(Request $request, Company $company): JsonResponse
    {
        if ($request->filled('to') && ! $request->filled('phone')) {
            $request->merge(['phone' => $request->input('to')]);
        }

        if ($request->filled('body') && ! $request->filled('message')) {
            $request->merge(['message' => $request->input('body')]);
        }

        $controller = app(APIController::class);

        if ($request->filled('template_name')) {
            $legacy = $controller->sendTemplateMessageToPhoneNumber($request);
        } elseif ($request->filled('action')) {
            $legacy = $controller->sendListMessageToPhoneNumber($request);
        } else {
            $legacy = $controller->sendMessageToPhoneNumber($request);
        }

        if (! $legacy instanceof JsonResponse) {
            $model = $legacy;
            $legacy = response()->json($model);
        }

        $payload = $legacy->getData(true);

        if (($payload['status'] ?? null) === 'error' || $legacy->getStatusCode() >= 400) {
            return $legacy;
        }

        $messageId = $payload['message_id'] ?? $payload['id'] ?? null;
        $message = $messageId ? Message::withoutGlobalScopes()->find($messageId) : null;

        return PublicApiResponse::success([
            'id' => $message?->id ?? $messageId,
            'channel' => 'whatsapp',
            'status' => $message && (int) $message->status === Message::STATUS_PENDING ? 'queued' : 'sent',
            'wamid' => $payload['message_wamid'] ?? $message?->fb_message_id,
            'contact_id' => $message?->contact_id,
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Message $message): array
    {
        return [
            'id' => $message->id,
            'contact_id' => $message->contact_id,
            'channel' => $message->channel ?? 'whatsapp',
            'body' => $message->value,
            'status' => $message->status,
            'wamid' => $message->fb_message_id,
            'is_note' => (bool) $message->is_note,
            'direction' => $message->is_message_by_contact ? 'inbound' : 'outbound',
            'created_at' => optional($message->created_at)?->toIso8601String(),
        ];
    }
}
