<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class ImpersonationService
{
    public function start(User $target): void
    {
        if (auth()->check()) {
            Session::put('impersonator_id', auth()->id());
        }

        Session::put('impersonate', $target->id);

        if ($target->company_id) {
            $company = Company::find($target->company_id);

            if ($company) {
                Session::put('company_id', $company->id);
                Session::put('company_currency', $company->currency);
                Session::put('company_convertion', $company->do_covertion);
            }
        }
    }

    public function stop(): void
    {
        Session::forget('impersonate');

        $impersonatorId = Session::pull('impersonator_id');

        if ($impersonatorId === null) {
            return;
        }

        $impersonator = User::find($impersonatorId);

        if ($impersonator === null) {
            return;
        }

        $this->restoreCompanySession($impersonator);
    }

    public function isActive(): bool
    {
        return Session::has('impersonate');
    }

    public function impersonator(): ?User
    {
        $id = Session::get('impersonator_id');

        return $id ? User::find($id) : null;
    }

    public function impersonatedUser(): ?User
    {
        $id = Session::get('impersonate');

        return $id ? User::find($id) : null;
    }

    protected function restoreCompanySession(User $impersonator): void
    {
        $companyId = Session::get('company_id');
        $company = null;

        if ($impersonator->hasRole('owner')) {
            if ($companyId) {
                $company = Company::query()
                    ->where('id', $companyId)
                    ->where('user_id', $impersonator->id)
                    ->first();
            }

            $company ??= $impersonator->companies()->oldest('id')->first();
        } elseif ($impersonator->hasRole('admin')) {
            if ($companyId) {
                $company = Company::find($companyId);
            }
        } else {
            $accessible = app(OrgAuthorization::class)->canAccessCompany($impersonator, (int) $companyId)
                ? Company::find($companyId)
                : null;

            $company = $accessible ?? $impersonator->accessibleCompanies()->first();
        }

        if ($company === null) {
            return;
        }

        Session::put('company_id', $company->id);
        Session::put('company_currency', $company->currency);
        Session::put('company_convertion', $company->do_covertion);
    }
}
