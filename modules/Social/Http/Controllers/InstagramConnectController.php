<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Social\Services\InstagramConnectService;
use RuntimeException;
use Throwable;

class InstagramConnectController extends Controller
{
    public function __construct(private readonly InstagramConnectService $instagramConnect)
    {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->instagramConnect->isConfigured()) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Instagram Social OAuth is not configured. Add SOCIAL_INSTAGRAM_CLIENT_ID and SOCIAL_INSTAGRAM_CLIENT_SECRET (or Facebook app credentials).'));
        }

        $company = $request->user()->currentCompany();

        if (! $company) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Select a company before connecting social accounts.'));
        }

        $state = Str::random(40);
        $request->session()->put('social.instagram.oauth_state', $state);
        $request->session()->put('social.instagram.company_id', $company->id);

        try {
            return redirect()->away($this->instagramConnect->authorizationUrl($state));
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
                ->withError(__('Instagram connection was cancelled or denied.'));
        }

        $expectedState = $request->session()->pull('social.instagram.oauth_state');
        $companyId = (int) $request->session()->pull('social.instagram.company_id');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->input('state'))) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Invalid Instagram OAuth state. Please try connecting again.'));
        }

        $company = $request->user()->currentCompany();

        if (! $company || (int) $company->id !== $companyId) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Company context changed during Instagram connect. Please try again.'));
        }

        $code = (string) $request->input('code', '');

        if ($code === '') {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Instagram did not return an authorization code.'));
        }

        try {
            $accounts = $this->instagramConnect->connectFromCode($companyId, $code);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('social.accounts.index')
                ->withError($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Instagram connect failed unexpectedly. Please try again.'));
        }

        return redirect()
            ->route('social.accounts.index')
            ->withStatus(__('Connected :count Instagram account(s).', ['count' => count($accounts)]));
    }
}
