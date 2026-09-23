<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Social\Services\ThreadsConnectService;
use RuntimeException;
use Throwable;

class ThreadsConnectController extends Controller
{
    use Concerns\EnforcesSocialAccountLimits;

    public function __construct(private readonly ThreadsConnectService $threadsConnect)
    {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->threadsConnect->isConfigured()) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Threads Social OAuth is not configured. Add SOCIAL_THREADS_CLIENT_ID and SOCIAL_THREADS_CLIENT_SECRET.'));
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
        $request->session()->put('social.threads.oauth_state', $state);
        $request->session()->put('social.threads.company_id', $company->id);

        try {
            return redirect()->away($this->threadsConnect->authorizationUrl($state));
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
                ->withError(__('Threads connection was cancelled or denied.'));
        }

        $expectedState = $request->session()->pull('social.threads.oauth_state');
        $companyId = (int) $request->session()->pull('social.threads.company_id');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->input('state'))) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Invalid Threads OAuth state. Please try connecting again.'));
        }

        $company = $request->user()->currentCompany();

        if (! $company || (int) $company->id !== $companyId) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Company context changed during Threads connect. Please try again.'));
        }

        $code = (string) $request->input('code', '');

        if ($code === '') {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Threads did not return an authorization code.'));
        }

        try {
            $accounts = $this->threadsConnect->connectFromCode($companyId, $code);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('social.accounts.index')
                ->withError($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Threads connect failed unexpectedly. Please try again.'));
        }

        return redirect()
            ->route('social.accounts.index')
            ->withStatus(__('Connected :count Threads account(s).', ['count' => count($accounts)]));
    }
}
