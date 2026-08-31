<?php

namespace Modules\Tiktok\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Messaging\ChannelConnection;
use Illuminate\Support\Facades\Log;

class TiktokWebhookSubscriber
{
    /**
     * @var array<int, string>
     */
    public const EVENT_TYPES = [
        'im_receive_msg',
        'im_send_msg',
        'im_mark_read_msg',
    ];

    public function __construct(private readonly TiktokClient $client)
    {
    }

    /**
     * @return array{attempted: bool, ok: bool, message: string, callback_url: ?string}
     */
    public function subscribeForConnection(ChannelConnection $connection): array
    {
        if ((string) config('services.tiktok.app_id', '') === ''
            || (string) config('services.tiktok.app_secret', '') === '') {
            return [
                'attempted' => false,
                'ok' => false,
                'message' => __('TikTok app_id and app_secret are not configured. Paste the webhook URL in TikTok manually.'),
                'callback_url' => null,
            ];
        }

        $token = $this->callbackToken($connection);
        if ($token === '') {
            return [
                'attempted' => false,
                'ok' => false,
                'message' => __('Missing webhook token for TikTok callback URL.'),
                'callback_url' => null,
            ];
        }

        $callbackUrl = route('messaging.webhook.receive', [
            'channel' => MessagingChannelType::Tiktok->value,
            'token' => $token,
        ]);

        $failures = [];
        foreach (self::EVENT_TYPES as $eventType) {
            $result = $this->client->updateWebhook($eventType, $callbackUrl);
            if ($result['ok']) {
                continue;
            }

            $failures[] = $eventType.': '.$result['message'];
            Log::warning('messaging.tiktok.webhook_subscribe_failed', [
                'connection_id' => $connection->id,
                'company_id' => $connection->company_id,
                'event_type' => $eventType,
                'code' => $result['code'],
                'message' => $result['message'],
            ]);
        }

        return [
            'attempted' => true,
            'ok' => $failures === [],
            'message' => implode('; ', $failures),
            'callback_url' => $callbackUrl,
        ];
    }

    public function resolveCallbackToken(?string $companyToken): string
    {
        $platform = trim((string) config('services.tiktok.webhook_token', ''));
        if ($platform !== '') {
            return $platform;
        }

        return trim((string) $companyToken);
    }

    public function callbackToken(ChannelConnection $connection): string
    {
        return $this->resolveCallbackToken($connection->webhook_token);
    }
}
