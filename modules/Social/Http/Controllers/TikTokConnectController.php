<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Social\Services\TikTokConnectService;
use RuntimeException;
use Throwable;

class TikTokConnectController extends Controller
{
    use Concerns\EnforcesSocialAccountLimits;

    public function __construct(private readonly TikTokConnectService $tikTokConnect)
    {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->tikTokConnect->isConfigured()) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('TikTok Social OAuth is not configured. Add SOCIAL_TIKTOK_CLIENT_KEY and SOCIAL_TIKTOK_CLIENT_SECRET.'));
        }

        $company = $request->user()->currentCompany();

        if (! $company) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Select a company before connecting social accounts.'));
        }

        if ($blocked = $this->ensureCanConnectAccounts($company, 1)) {
            return $blocked;
        }

        $state = Str::random(40);
        $request->session()->put('social.tiktok.oauth_state', $state);
        $request->session()->put('social.tiktok.company_id', $company->id);

        try {
            return redirect()->away($this->tikTokConnect->authorizationUrl($state));
        } catch (Throwable $e) {
            return redirect()
                ->route('social.accounts.index')
                ->withError($e->getMessage());
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('TikTok connection was cancelled or denied.'));
        }

        $expectedState = $request->session()->pull('social.tiktok.oauth_state');
        $companyId = (int) $request->session()->pull('social.tiktok.company_id');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->input('state'))) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Invalid TikTok OAuth state. Please try connecting again.'));
        }

        $company = $request->user()->currentCompany();

        if (! $company || (int) $company->id !== $companyId) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Company context changed during TikTok connect. Please try again.'));
        }

        $code = (string) $request->input('code', '');

        if ($code === '') {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('TikTok did not return an authorization code.'));
        }

        try {
            $accounts = $this->tikTokConnect->connectFromCode($companyId, $code);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('social.accounts.index')
                ->withError($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('social.accounts.index')
                ->withError(__('TikTok connect failed unexpectedly. Please try again.'));
        }

        return redirect()
            ->route('social.accounts.index')
            ->withStatus(__('Connected :count TikTok account(s).', ['count' => count($accounts)]));
    }
}
