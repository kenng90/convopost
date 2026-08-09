<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MetaInstagramCredentialResolver
{
    protected string $graphVersion = 'v19.0';

    /**
     * Normalize Instagram setup credentials.
     *
     * Graph Explorer often pastes a User token (EAAG...). Instagram replies require
     * the linked Facebook Page access token for that Page, with instagram_manage_messages.
     *
     * @return array{page_id: string, instagram_account_id: string, page_access_token: string, page_name: ?string, ig_username: ?string}
     */
    public function resolve(string $pageId, string $instagramAccountId, string $accessToken): array
    {
        $pageId = trim($pageId);
        $instagramAccountId = trim($instagramAccountId);
        $accessToken = trim($accessToken);

        if ($accessToken === '' || str_starts_with($accessToken, 'IGAA')) {
            throw ValidationException::withMessages([
                'page_access_token' => __('Use a Facebook Page access token (starts with EAA), not an Instagram IGAA token.'),
            ]);
        }

        $this->assertRequiredScopes($accessToken);

        $response = Http::withToken($accessToken)->get(
            "https://graph.facebook.com/{$this->graphVersion}/{$pageId}",
            [
                'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            ],
        );

        if (! $response->successful()) {
            $error = data_get($response->json(), 'error.message', $response->body());

            throw ValidationException::withMessages([
                'page_access_token' => __('Could not validate Page credentials: :error', ['error' => $error]),
            ]);
        }

        $linkedIgId = (string) data_get($response->json(), 'instagram_business_account.id', '');
        $igUsername = data_get($response->json(), 'instagram_business_account.username');
        $pageName = data_get($response->json(), 'name');

        if ($linkedIgId === '') {
            throw ValidationException::withMessages([
                'page_id' => __('This Facebook Page is not linked to an Instagram Professional account (Meta error 2534013). In Meta Business Suite / Instagram settings, link the IG account that receives DMs to this Page, then save again.'),
            ]);
        }

        if ($linkedIgId !== $instagramAccountId) {
            throw ValidationException::withMessages([
                'instagram_account_id' => __('Instagram account ID must be :expected (linked to this Page), not :provided. Webhooks for :expected will not match replies sent as this Page.', [
                    'expected' => $linkedIgId,
                    'provided' => $instagramAccountId,
                ]),
            ]);
        }

        // If a User token was pasted, Graph returns a Page access_token on the Page node.
        $pageToken = (string) data_get($response->json(), 'access_token', '');
        if ($pageToken === '') {
            $pageToken = $accessToken;
        }

        return [
            'page_id' => (string) data_get($response->json(), 'id', $pageId),
            'instagram_account_id' => $linkedIgId,
            'page_access_token' => $pageToken,
            'page_name' => is_string($pageName) ? $pageName : null,
            'ig_username' => is_string($igUsername) ? $igUsername : null,
        ];
    }

    protected function assertRequiredScopes(string $accessToken): void
    {
        $debug = Http::withToken($accessToken)->get(
            "https://graph.facebook.com/{$this->graphVersion}/debug_token",
            ['input_token' => $accessToken],
        );

        if (! $debug->successful()) {
            return;
        }

        $scopes = collect(data_get($debug->json(), 'data.scopes', []))
            ->map(fn ($scope) => (string) $scope)
            ->all();

        if ($scopes === []) {
            return;
        }

        $required = ['pages_messaging', 'instagram_manage_messages'];
        $missing = array_values(array_diff($required, $scopes));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'page_access_token' => __('Token is missing required permission(s): :missing. In Graph API Explorer, add these scopes, generate a new User token, then GET /me/accounts and paste the Page access_token (or paste the User token and we will exchange it).', [
                    'missing' => implode(', ', $missing),
                ]),
            ]);
        }
    }
}
