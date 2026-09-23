<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Social\Services\XConnectService;
use RuntimeException;
use Throwable;

class XConnectController extends Controller
{
    use Concerns\EnforcesSocialAccountLimits;

    public function __construct(private readonly XConnectService $xConnect)
    {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->xConnect->isConfigured()) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('X Social OAuth is not configured. Add SOCIAL_X_CLIENT_ID and SOCIAL_X_CLIENT_SECRET.'));
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
        $codeVerifier = Str::random(64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $request->session()->put('social.x.oauth_state', $state);
        $request->session()->put('social.x.company_id', $company->id);
        $request->session()->put('social.x.code_verifier', $codeVerifier);

        try {
            return redirect()->away($this->xConnect->authorizationUrl($state, $codeChallenge));
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
                ->withError(__('X connection was cancelled or denied.'));
        }

        $expectedState = $request->session()->pull('social.x.oauth_state');
        $companyId = (int) $request->session()->pull('social.x.company_id');
        $codeVerifier = (string) $request->session()->pull('social.x.code_verifier', '');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->input('state'))) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Invalid X OAuth state. Please try connecting again.'));
        }

        if ($codeVerifier === '') {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Missing X PKCE verifier. Please try connecting again.'));
        }

        $company = $request->user()->currentCompany();

        if (! $company || (int) $company->id !== $companyId) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Company context changed during X connect. Please try again.'));
        }

        $code = (string) $request->input('code', '');

        if ($code === '') {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('X did not return an authorization code.'));
        }

        try {
            $accounts = $this->xConnect->connectFromCode($companyId, $code, $codeVerifier);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('social.accounts.index')
                ->withError($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('social.accounts.index')
                ->withError(__('X connect failed unexpectedly. Please try again.'));
        }

        return redirect()
            ->route('social.accounts.index')
            ->withStatus(__('Connected :count X account(s).', ['count' => count($accounts)]));
    }
}
