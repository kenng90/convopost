<?php

namespace Modules\Social\Services;

use Illuminate\Http\Request;
use Modules\Social\Models\SocialOfferLink;

class SocialOfferTrackingService
{
    public const SESSION_KEY = 'social_attribution';

    public function trackedUrl(SocialOfferLink $link): string
    {
        return route('social.offer.redirect', ['token' => $link->tracking_token], true);
    }

    public function findByToken(string $token): ?SocialOfferLink
    {
        return SocialOfferLink::withoutGlobalScopes()
            ->where('tracking_token', $token)
            ->first();
    }

    public function rememberInSession(Request $request, SocialOfferLink $link): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'social_post_id' => $link->social_post_id,
            'social_offer_link_id' => $link->id,
            'tracking_token' => $link->tracking_token,
            'clicked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array{social_post_id: int|null, social_offer_link_id: int|null}|null
     */
    public function attributionFromSession(Request $request): ?array
    {
        $payload = $request->session()->get(self::SESSION_KEY);

        if (! is_array($payload)) {
            return null;
        }

        $postId = isset($payload['social_post_id']) ? (int) $payload['social_post_id'] : null;
        $linkId = isset($payload['social_offer_link_id']) ? (int) $payload['social_offer_link_id'] : null;

        if (! $postId && ! $linkId) {
            return null;
        }

        return [
            'social_post_id' => $postId ?: null,
            'social_offer_link_id' => $linkId ?: null,
        ];
    }

    public function clearSession(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
