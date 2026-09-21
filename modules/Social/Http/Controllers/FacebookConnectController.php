<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Social\Services\FacebookConnectService;
use RuntimeException;
use Throwable;

class FacebookConnectController extends Controller
{
    public function __construct(private readonly FacebookConnectService $facebookConnect)
    {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->facebookConnect->isConfigured()) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Facebook Social OAuth is not configured. Add SOCIAL_FACEBOOK_CLIENT_ID and SOCIAL_FACEBOOK_CLIENT_SECRET.'));
        }

        $company = $request->user()->currentCompany();

        if (! $company) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Select a company before connecting social accounts.'));
        }

        $state = Str::random(40);
        $request->session()->put('social.facebook.oauth_state', $state);
        $request->session()->put('social.facebook.company_id', $company->id);

        try {
            return redirect()->away($this->facebookConnect->authorizationUrl($state));
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
                ->withError(__('Facebook connection was cancelled or denied.'));
        }

        $expectedState = $request->session()->pull('social.facebook.oauth_state');
        $companyId = (int) $request->session()->pull('social.facebook.company_id');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->input('state'))) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Invalid Facebook OAuth state. Please try connecting again.'));
        }

        $company = $request->user()->currentCompany();

        if (! $company || (int) $company->id !== $companyId) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Company context changed during Facebook connect. Please try again.'));
        }

        $code = (string) $request->input('code', '');

        if ($code === '') {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Facebook did not return an authorization code.'));
        }

        try {
            $accounts = $this->facebookConnect->connectFromCode($companyId, $code);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('social.accounts.index')
                ->withError($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Facebook connect failed unexpectedly. Please try again.'));
        }

        return redirect()
            ->route('social.accounts.index')
            ->withStatus(__('Connected :count Facebook Page(s).', ['count' => count($accounts)]));
    }
}
