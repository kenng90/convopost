<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Social\Services\PinterestConnectService;
use RuntimeException;
use Throwable;

class PinterestConnectController extends Controller
{
    use Concerns\EnforcesSocialAccountLimits;

    public function __construct(private readonly PinterestConnectService $pinterestConnect)
    {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->pinterestConnect->isConfigured()) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Pinterest Social OAuth is not configured. Add SOCIAL_PINTEREST_CLIENT_ID and SOCIAL_PINTEREST_CLIENT_SECRET.'));
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
        $request->session()->put('social.pinterest.oauth_state', $state);
        $request->session()->put('social.pinterest.company_id', $company->id);

        try {
            return redirect()->away($this->pinterestConnect->authorizationUrl($state));
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
                ->withError(__('Pinterest connection was cancelled or denied.'));
        }

        $expectedState = $request->session()->pull('social.pinterest.oauth_state');
        $companyId = (int) $request->session()->pull('social.pinterest.company_id');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->input('state'))) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Invalid Pinterest OAuth state. Please try connecting again.'));
        }

        $company = $request->user()->currentCompany();

        if (! $company || (int) $company->id !== $companyId) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Company context changed during Pinterest connect. Please try again.'));
        }

        $code = (string) $request->input('code', '');

        if ($code === '') {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Pinterest did not return an authorization code.'));
        }

        try {
            $accounts = $this->pinterestConnect->connectFromCode($companyId, $code);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('social.accounts.index')
                ->withError($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Pinterest connect failed unexpectedly. Please try again.'));
        }

        return redirect()
            ->route('social.accounts.index')
            ->withStatus(__('Connected :count Pinterest board(s).', ['count' => count($accounts)]));
    }
}
