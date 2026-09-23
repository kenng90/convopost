<?php

namespace Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Social\Services\SocialOnboardingService;

class CalendarController extends Controller
{
    public function __invoke(Request $request, SocialOnboardingService $onboarding): View
    {
        $company = $request->user()?->currentCompany();

        return view('social::calendar.index', [
            'onboarding' => $onboarding->forCompany($company),
            'workspace' => $company,
        ]);
    }
}
