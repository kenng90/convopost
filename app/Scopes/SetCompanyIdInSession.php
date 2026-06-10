<?php

namespace App\Scopes;

use App\Models\User;

class SetCompanyIdInSession
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $user = User::find($event->user->id);

        if ($user === null) {
            return;
        }

        $company = $user->company_id
            ? $user->companies()->where('id', $user->company_id)->first()
            : null;

        $company ??= $user->companies()->oldest('id')->first();

        if ($company) {
            session(['company_id' => $company->id]);
            session(['company_currency' => $company->currency]);
            session(['company_convertion' => $company->do_covertion]);

            if ($user->hasRole('owner') && $user->company_id === null) {
                $user->forceFill(['company_id' => $company->id])->save();
            }
        }

    }
}
