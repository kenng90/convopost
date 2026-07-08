<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConvoConnectCredentialsRequest;
use App\Models\Company;
use App\Models\Config;
use App\Services\HostPinnacle\HostPinnacleAdminPresenter;
use App\Services\HostPinnacle\HostPinnacleCredentials;
use App\Services\HostPinnacle\HostPinnacleCreditSync;
use App\Services\HostPinnacle\HostPinnacleProvisioner;
use App\Support\ConvoConnectBrand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HostPinnacleAdminController extends Controller
{
    public function __construct(
        private readonly HostPinnacleAdminPresenter $presenter,
        private readonly HostPinnacleProvisioner $provisioner,
        private readonly HostPinnacleCreditSync $creditSync,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizePlatformAdmin();

        $query = Company::query()->with('user')->orderByDesc('id');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->string('name')->toString().'%');
        }

        if ($request->filled('sender_status')) {
            $status = $request->string('sender_status')->toString();

            if ($status === 'not_provisioned') {
                $provisionedIds = Config::query()
                    ->where('model_type', 'App\Models\Company')
                    ->where('key', 'HOSTPINNACLE_API_KEY')
                    ->where('value', '!=', '')
                    ->pluck('model_id');

                $query->whereNotIn('id', $provisionedIds);
            } else {
                $matchingIds = Config::query()
                    ->where('model_type', 'App\Models\Company')
                    ->where('key', 'HOSTPINNACLE_SENDER_STATUS')
                    ->where('value', $status)
                    ->pluck('model_id');

                $query->whereIn('id', $matchingIds);
            }
        }

        $companies = $query->paginate(25)->through(
            fn (Company $company) => $this->presenter->summaryForCompany($company)
        );

        return view('admin.convoconnect.index', [
            'companies' => $companies,
            'filters' => [
                'name' => $request->string('name')->toString(),
                'sender_status' => $request->string('sender_status')->toString(),
            ],
            'platformEnabled' => (bool) config('hostpinnacle.enabled', false),
            'resellerConfigured' => HostPinnacleCredentials::reseller() !== null,
            'presenter' => $this->presenter,
        ]);
    }

    public function show(Company $company, Request $request): View
    {
        $this->authorizePlatformAdmin();

        return view('admin.convoconnect.show', [
            'company' => $company,
            'detail' => $this->presenter->detailForCompany($company, $request->boolean('live')),
            'presenter' => $this->presenter,
        ]);
    }

    public function provision(Company $company): RedirectResponse
    {
        $this->authorizePlatformAdmin();

        if ($this->provisioner->provision($company)) {
            return redirect()
                ->route('admin.convoconnect.show', $company)
                ->withStatus(__(':brand sub-account provisioned for :name.', [
                    'brand' => ConvoConnectBrand::name(),
                    'name' => $company->name,
                ]));
        }

        return redirect()
            ->route('admin.convoconnect.show', $company)
            ->with('error', __(':brand provisioning failed. Check logs and platform credentials.', [
                'brand' => ConvoConnectBrand::name(),
            ]));
    }

    public function resumeProvision(Company $company): RedirectResponse
    {
        $this->authorizePlatformAdmin();

        if ($this->provisioner->resume($company)) {
            return redirect()
                ->route('admin.convoconnect.show', $company)
                ->withStatus(__(':brand credentials linked for :name. Existing gateway user was reused.', [
                    'brand' => ConvoConnectBrand::name(),
                    'name' => $company->name,
                ]));
        }

        return redirect()
            ->route('admin.convoconnect.show', $company)
            ->with('error', __('Failed to resume :brand provisioning. Confirm the gateway username in the form below, then try again.', [
                'brand' => ConvoConnectBrand::name(),
            ]));
    }

    public function approveSender(Company $company): RedirectResponse
    {
        $this->authorizePlatformAdmin();

        $company->setConfig('HOSTPINNACLE_SENDER_STATUS', 'approved');

        return redirect()
            ->route('admin.convoconnect.show', $company)
            ->withStatus(__('Sender ID marked as approved for :name.', ['name' => $company->name]));
    }

    public function syncCredits(Company $company, Request $request): RedirectResponse
    {
        $this->authorizePlatformAdmin();

        $credits = (float) $request->input('credits', 0);
        if ($credits <= 0) {
            return redirect()
                ->route('admin.convoconnect.show', $company)
                ->with('error', __('Enter a credit amount greater than zero.'));
        }

        if ($this->creditSync->syncCreditsToCompany($company, $credits, 'Manual admin sync')) {
            return redirect()
                ->route('admin.convoconnect.show', ['company' => $company, 'live' => 1])
                ->withStatus(__('Added :credits SMS credits to :brand sub-account.', [
                    'credits' => $credits,
                    'brand' => ConvoConnectBrand::name(),
                ]));
        }

        return redirect()
            ->route('admin.convoconnect.show', $company)
            ->with('error', __('Failed to sync credits to :brand.', ['brand' => ConvoConnectBrand::name()]));
    }

    public function storeCredentials(Company $company, StoreConvoConnectCredentialsRequest $request): RedirectResponse
    {
        $this->authorizePlatformAdmin();

        $company->setMultipleConfig($request->credentialsPayload($company));

        return redirect()
            ->route('admin.convoconnect.show', $company)
            ->withStatus(__('ConvoConnect credentials saved for :name.', ['name' => $company->name]));
    }

    private function authorizePlatformAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }
}
