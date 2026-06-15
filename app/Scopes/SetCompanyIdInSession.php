<?php

namespace App\Scopes;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;

class SetCompanyIdInSession
{
    public function handle(object $event): void
    {
        $user = User::find($event->user->id);

        if ($user === null) {
            return;
        }

        if ($user->hasRole('owner')) {
            $company = $user->company_id
                ? $user->companies()->where('id', $user->company_id)->first()
                : null;

            $company ??= $user->companies()->oldest('id')->first();

            if ($company) {
                session(['company_id' => $company->id]);
                session(['company_currency' => $company->currency]);
                session(['company_convertion' => $company->do_covertion]);

                if ($user->company_id === null) {
                    $user->forceFill(['company_id' => $company->id])->save();
                }
            }

            return;
        }

        $membership = CompanyMembership::query()
            ->where('user_id', $user->id)
            ->where('status', CompanyMembership::STATUS_ACTIVE)
            ->with('company')
            ->oldest('id')
            ->first();

        if ($membership !== null && $membership->company instanceof Company) {
            session(['company_id' => $membership->company_id]);
            session(['company_currency' => $membership->company->currency]);
            session(['company_convertion' => $membership->company->do_covertion]);

            if ($user->company_id === null) {
                $user->forceFill(['company_id' => $membership->company_id])->save();
            }

            return;
        }

        if ($user->company_id !== null) {
            $company = Company::find($user->company_id);

            if ($company !== null) {
                session(['company_id' => $company->id]);
                session(['company_currency' => $company->currency]);
                session(['company_convertion' => $company->do_covertion]);
            }
        }
    }
}
