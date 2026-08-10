<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\Response;
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

        $page = $this->loadPageNode($pageId, $accessToken);

        $linkedIgId = (string) data_get($page, 'instagram_business_account.id', '');
        $igUsername = data_get($page, 'instagram_business_account.username');
        $pageName = data_get($page, 'name');

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
        $pageToken = (string) data_get($page, 'access_token', '');
        if ($pageToken === '') {
            $pageToken = $accessToken;
        }

        return [
            'page_id' => (string) data_get($page, 'id', $pageId),
            'instagram_account_id' => $linkedIgId,
            'page_access_token' => $pageToken,
            'page_name' => is_string($pageName) ? $pageName : null,
            'ig_username' => is_string($igUsername) ? $igUsername : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadPageNode(string $pageId, string $accessToken): array
    {
        $fields = 'id,name,access_token,instagram_business_account{id,username}';

        $byId = Http::withToken($accessToken)->get(
            "https://graph.facebook.com/{$this->graphVersion}/{$pageId}",
            ['fields' => $fields],
        );

        if ($byId->successful()) {
            return $byId->json();
        }

        // Page tokens usually resolve /me as the Page itself (often works without pages_read_engagement).
        $me = Http::withToken($accessToken)->get(
            "https://graph.facebook.com/{$this->graphVersion}/me",
            ['fields' => $fields],
        );

        if ($me->successful() && (string) data_get($me->json(), 'id') === $pageId) {
            return $me->json();
        }

        // User tokens: list managed Pages and pick the matching one.
        $accounts = Http::withToken($accessToken)->get(
            "https://graph.facebook.com/{$this->graphVersion}/me/accounts",
            ['fields' => $fields, 'limit' => 100],
        );

        if ($accounts->successful()) {
            foreach (data_get($accounts->json(), 'data', []) as $account) {
                if ((string) data_get($account, 'id') === $pageId) {
                    return is_array($account) ? $account : [];
                }
            }

            throw ValidationException::withMessages([
                'page_id' => __('Page :page was not found in /me/accounts for this token. Confirm the Page ID and that your Facebook user administers that Page.', [
                    'page' => $pageId,
                ]),
            ]);
        }

        throw ValidationException::withMessages([
            'page_access_token' => $this->humanizePageLoadFailure($byId, $accounts),
        ]);
    }

    protected function humanizePageLoadFailure(Response $pageResponse, Response $accountsResponse): string
    {
        $error = (string) data_get($pageResponse->json(), 'error.message', $pageResponse->body());

        if (
            str_contains($error, 'pages_read_engagement')
            || str_contains($error, 'Page Public Content Access')
            || str_contains($error, 'Page Public Metadata Access')
            || str_contains($error, 'missing permission')
        ) {
            return __('Could not read this Page with the pasted token. Regenerate a User token in Graph API Explorer that includes pages_show_list, pages_read_engagement, pages_messaging, pages_manage_metadata, and instagram_manage_messages. Then either paste that User token (we will exchange it via /me/accounts) or paste the Page access_token from GET /me/accounts.');
        }

        $accountsError = (string) data_get($accountsResponse->json(), 'error.message', '');

        return __('Could not validate Page credentials: :error', [
            'error' => trim($error.($accountsError !== '' ? ' /me/accounts: '.$accountsError : '')),
        ]);
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
                'page_access_token' => __('Token is missing required permission(s): :missing. In Graph API Explorer, add these scopes (plus pages_show_list and pages_read_engagement for User tokens), generate a new token, then GET /me/accounts and paste the Page access_token (or paste the User token and we will exchange it).', [
                    'missing' => implode(', ', $missing),
                ]),
            ]);
        }
    }
}
