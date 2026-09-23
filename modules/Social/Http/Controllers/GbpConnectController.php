<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Social\Services\GbpConnectService;
use RuntimeException;
use Throwable;

class GbpConnectController extends Controller
{
    use Concerns\EnforcesSocialAccountLimits;

    public function __construct(private readonly GbpConnectService $gbpConnect)
    {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->gbpConnect->isConfigured()) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Google Business Profile OAuth is not configured. Add SOCIAL_GBP_CLIENT_ID and SOCIAL_GBP_CLIENT_SECRET (or GOOGLE_CLIENT_*).'));
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
        $request->session()->put('social.gbp.oauth_state', $state);
        $request->session()->put('social.gbp.company_id', $company->id);

        try {
            return redirect()->away($this->gbpConnect->authorizationUrl($state));
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
                ->withError(__('Google Business Profile connection was cancelled or denied.'));
        }

        $expectedState = $request->session()->pull('social.gbp.oauth_state');
        $companyId = (int) $request->session()->pull('social.gbp.company_id');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->input('state'))) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Invalid Google Business Profile OAuth state. Please try connecting again.'));
        }

        $company = $request->user()->currentCompany();

        if (! $company || (int) $company->id !== $companyId) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Company context changed during Google Business Profile connect. Please try again.'));
        }

        $code = (string) $request->input('code', '');

        if ($code === '') {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Google Business Profile did not return an authorization code.'));
        }

        try {
            $accounts = $this->gbpConnect->connectFromCode($companyId, $code);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('social.accounts.index')
                ->withError($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Google Business Profile connect failed unexpectedly. Please try again.'));
        }

        return redirect()
            ->route('social.accounts.index')
            ->withStatus(__('Connected :count Google Business location(s).', ['count' => count($accounts)]));
    }
}
