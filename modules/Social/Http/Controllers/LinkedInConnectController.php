<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Social\Services\LinkedInConnectService;
use RuntimeException;
use Throwable;

class LinkedInConnectController extends Controller
{
    public function __construct(private readonly LinkedInConnectService $linkedInConnect)
    {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->linkedInConnect->isConfigured()) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('LinkedIn Social OAuth is not configured. Add SOCIAL_LINKEDIN_CLIENT_ID and SOCIAL_LINKEDIN_CLIENT_SECRET.'));
        }

        $company = $request->user()->currentCompany();

        if (! $company) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Select a company before connecting social accounts.'));
        }

        $state = Str::random(40);
        $request->session()->put('social.linkedin.oauth_state', $state);
        $request->session()->put('social.linkedin.company_id', $company->id);

        try {
            return redirect()->away($this->linkedInConnect->authorizationUrl($state));
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
                ->withError(__('LinkedIn connection was cancelled or denied.'));
        }

        $expectedState = $request->session()->pull('social.linkedin.oauth_state');
        $companyId = (int) $request->session()->pull('social.linkedin.company_id');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->input('state'))) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Invalid LinkedIn OAuth state. Please try connecting again.'));
        }

        $company = $request->user()->currentCompany();

        if (! $company || (int) $company->id !== $companyId) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Company context changed during LinkedIn connect. Please try again.'));
        }

        $code = (string) $request->input('code', '');

        if ($code === '') {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('LinkedIn did not return an authorization code.'));
        }

        try {
            $accounts = $this->linkedInConnect->connectFromCode($companyId, $code);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('social.accounts.index')
                ->withError($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('social.accounts.index')
                ->withError(__('LinkedIn connect failed unexpectedly. Please try again.'));
        }

        return redirect()
            ->route('social.accounts.index')
            ->withStatus(__('Connected :count LinkedIn account(s).', ['count' => count($accounts)]));
    }
}
