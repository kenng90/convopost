<?php

namespace Modules\Social\Publishing;

use Illuminate\Support\Facades\Http;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;

trait PostsFirstComment
{
    /**
     * Post a first comment after a successful publish. Failures are returned in meta only —
     * the original post remains published.
     *
     * @return array{first_comment_id?: string, first_comment_error?: string}
     */
    protected function postFirstComment(
        SocialAccount $account,
        SocialPostVersion $version,
        string $providerPostId,
        string $graphBaseUrl,
    ): array {
        $comment = trim((string) ($version->first_comment ?? ''));

        if ($comment === '' || $providerPostId === '') {
            return [];
        }

        $token = $account->getAccessToken();

        if (! $token) {
            return ['first_comment_error' => 'Missing access token for first comment.'];
        }

        $response = Http::asForm()->post(
            rtrim($graphBaseUrl, '/').'/'.$providerPostId.'/comments',
            [
                'message' => mb_substr($comment, 0, 8000),
                'access_token' => $token,
            ]
        );

        if (! $response->successful()) {
            return [
                'first_comment_error' => (string) (data_get($response->json(), 'error.message') ?? $response->body()),
            ];
        }

        $commentId = (string) (data_get($response->json(), 'id') ?? '');

        if ($commentId === '') {
            return ['first_comment_error' => 'Comment API did not return an id.'];
        }

        return ['first_comment_id' => $commentId];
    }
}
