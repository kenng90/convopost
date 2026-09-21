<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->currentCompany();

        $accounts = SocialAccount::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->orderByDesc('id')
            ->paginate(config('settings.paginate', 20));

        $providers = collect(SocialProvider::publishable())
            ->filter(fn (SocialProvider $provider) => (bool) config('social.providers.'.$provider->value.'.enabled', true))
            ->map(fn (SocialProvider $provider) => [
                'value' => $provider->value,
                'label' => config('social.providers.'.$provider->value.'.label', $provider->label()),
                'connect_route' => 'social.accounts.connect.'.$provider->value,
            ])
            ->values()
            ->all();

        return view('social::accounts.index', [
            'accounts' => $accounts,
            'providers' => $providers,
        ]);
    }

    public function destroy(Request $request, SocialAccount $account): RedirectResponse
    {
        $company = $request->user()->currentCompany();

        if (! $company || (int) $account->company_id !== (int) $company->id) {
            abort(404);
        }

        $account->status = 'inactive';
        $account->save();
        $account->delete();

        return redirect()
            ->route('social.accounts.index')
            ->withStatus(__('Social account disconnected.'));
    }
}
