<?php

namespace Modules\Social\Http\Controllers\Concerns;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Modules\Social\Services\SocialAccountPlanLimit;

trait EnforcesSocialAccountLimits
{
    protected function ensureCanConnectAccounts(Company $company, int $additional = 1): ?RedirectResponse
    {
        $limits = app(SocialAccountPlanLimit::class);

        if ($limits->canAdd($company, $additional)) {
            return null;
        }

        return redirect()
            ->route('social.accounts.index')
            ->withError($limits->limitExceededMessage($company, $additional));
    }
}
