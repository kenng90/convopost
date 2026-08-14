<?php

namespace App\Services\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaPageLinkService
{
    public const PAGE_SUBSCRIBED_FIELDS = [
        'messages',
        'messaging_postbacks',
        'standby',
        'messaging_handovers',
    ];

    public function __construct(
        private readonly ChannelConnectionService $connections,
    ) {
    }

    /**
     * @return array{ok: bool, page_id: string, instagram_account_id: ?string, page_name: ?string, error?: string}
     */
    public function link(Company $company, string $pageId, ?string $instagramAccountId = null): array
    {
        $pageId = trim($pageId);
        $instagramAccountId = $instagramAccountId !== null ? trim($instagramAccountId) : null;

        if ($pageId === '') {
            return ['ok' => false, 'page_id' => '', 'instagram_account_id' => null, 'page_name' => null, 'error' => 'Page ID is required.'];
        }

        $userToken = $this->bestUserToken($company);
        $pageToken = $userToken;
        $pageName = null;
        $linkedIg = $instagramAccountId;

        if ($userToken !== '') {
            $pageNode = Http::withToken($userToken)->get($this->graphUrl().'/'.$pageId, [
                'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            ]);

            if ($pageNode->successful()) {
                $exchanged = (string) data_get($pageNode->json(), 'access_token', '');
                if ($exchanged !== '') {
                    $pageToken = $exchanged;
                }

                $pageName = data_get($pageNode->json(), 'name');
                $fromGraph = (string) data_get($pageNode->json(), 'instagram_business_account.id', '');
                if ($fromGraph !== '') {
                    $linkedIg = $fromGraph;
                }
            } else {
                Log::warning('messaging.meta_page_link.graph_failed', [
                    'company_id' => $company->id,
                    'page_id' => $pageId,
                    'status' => $pageNode->status(),
                    'body' => $pageNode->body(),
                ]);
            }
        }

        if ($pageToken === '') {
            return [
                'ok' => false,
                'page_id' => $pageId,
                'instagram_account_id' => $linkedIg,
                'page_name' => $pageName,
                'error' => 'No access token on this company. Re-run omnichannel Embedded Signup for this workspace.',
            ];
        }

        $this->subscribePageWebhooks($pageToken, $pageId);

        $webhookToken = (string) $company->getConfig('plain_token', '');

        $instagram = $this->connections->upsertMetaConnection(
            $company,
            MessagingChannelType::Instagram,
            $pageId,
            $pageToken,
            array_filter([
                'instagram_account_id' => $linkedIg,
                'page_name' => $pageName,
            ]),
        );
        $messenger = $this->connections->upsertMetaConnection(
            $company,
            MessagingChannelType::Messenger,
            $pageId,
            $pageToken,
            array_filter([
                'page_name' => $pageName,
            ]),
        );

        if ($webhookToken !== '') {
            $this->connections->storeWebhookToken($instagram, $webhookToken);
            $this->connections->storeWebhookToken($messenger, $webhookToken);
        }

        $company->setConfig('instagram_page_id', $pageId);
        $company->setConfig('instagram_page_access_token', $pageToken);
        $company->setConfig('messenger_page_id', $pageId);
        $company->setConfig('messenger_page_access_token', $pageToken);
        if ($linkedIg) {
            $company->setConfig('instagram_account_id', $linkedIg);
        }

        return [
            'ok' => true,
            'page_id' => $pageId,
            'instagram_account_id' => $linkedIg,
            'page_name' => is_string($pageName) ? $pageName : null,
        ];
    }

    private function bestUserToken(Company $company): string
    {
        foreach ([
            'whatsapp_permanent_access_token',
            'instagram_page_access_token',
            'messenger_page_access_token',
        ] as $key) {
            $token = (string) $company->getConfig($key, '');
            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }

    private function graphUrl(): string
    {
        return 'https://graph.facebook.com/'.config('embeddedlogin.graph_version', 'v22.0');
    }

    private function subscribePageWebhooks(string $accessToken, string $pageId): void
    {
        $response = Http::withToken($accessToken)->asForm()->post($this->graphUrl().'/'.$pageId.'/subscribed_apps', [
            'subscribed_fields' => implode(',', self::PAGE_SUBSCRIBED_FIELDS),
        ]);

        if (! $response->successful()) {
            Log::warning('messaging.meta_page_link.subscribe_failed', [
                'page_id' => $pageId,
                'body' => $response->body(),
            ]);
        }
    }
}
