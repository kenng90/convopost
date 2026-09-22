<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Social\Models\SocialOfferLink;
use Modules\Social\Services\SocialOfferTrackingService;

class OfferRedirectController extends Controller
{
    public function __construct(private readonly SocialOfferTrackingService $tracking)
    {
    }

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $link = $this->tracking->findByToken($token);

        if (! $link) {
            abort(404);
        }

        $link->recordClick();
        $this->tracking->recordClickEvent($request, $link);
        $this->tracking->rememberInSession($request, $link);

        return redirect()->away($this->destination($link));
    }

    protected function destination(SocialOfferLink $link): string
    {
        $url = trim($link->destinationUrl());

        if ($url === '' || $url === '/') {
            return url('/');
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return url($url);
        }

        return 'https://'.ltrim($url, '/');
    }
}
