<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Social\Services\YouTubeConnectService;
use RuntimeException;
use Throwable;

class YouTubeConnectController extends Controller
{
    use Concerns\EnforcesSocialAccountLimits;

    public function __construct(private readonly YouTubeConnectService $youTubeConnect)
    {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->youTubeConnect->isConfigured()) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('YouTube Social OAuth is not configured. Add SOCIAL_YOUTUBE_CLIENT_ID and SOCIAL_YOUTUBE_CLIENT_SECRET (or GOOGLE_CLIENT_*).'));
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
        $request->session()->put('social.youtube.oauth_state', $state);
        $request->session()->put('social.youtube.company_id', $company->id);

        try {
            return redirect()->away($this->youTubeConnect->authorizationUrl($state));
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
                ->withError(__('YouTube connection was cancelled or denied.'));
        }

        $expectedState = $request->session()->pull('social.youtube.oauth_state');
        $companyId = (int) $request->session()->pull('social.youtube.company_id');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->input('state'))) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Invalid YouTube OAuth state. Please try connecting again.'));
        }

        $company = $request->user()->currentCompany();

        if (! $company || (int) $company->id !== $companyId) {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('Company context changed during YouTube connect. Please try again.'));
        }

        $code = (string) $request->input('code', '');

        if ($code === '') {
            return redirect()
                ->route('social.accounts.index')
                ->withError(__('YouTube did not return an authorization code.'));
        }

        try {
            $accounts = $this->youTubeConnect->connectFromCode($companyId, $code);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('social.accounts.index')
                ->withError($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('social.accounts.index')
                ->withError(__('YouTube connect failed unexpectedly. Please try again.'));
        }

        return redirect()
            ->route('social.accounts.index')
            ->withStatus(__('Connected :count YouTube channel(s).', ['count' => count($accounts)]));
    }
}
