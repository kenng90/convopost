<?php

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrgAuthorization;
use App\Services\Platform\ManagedAiService;
use App\Services\Platform\RevenueDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function index(): array|RedirectResponse
    {
        $user = auth()->user();

        if (! app(OrgAuthorization::class)->canViewDashboardMetrics($user)) {
            return [];
        }

        $company = $this->getCompany();
        if (! $company) {
            return [];
        }

        return app(RevenueDashboardService::class)->widgets($company);
    }

    public function managedAiStatus(): JsonResponse
    {
        $this->ownerAndStaffOnly();

        return response()->json(
            app(ManagedAiService::class)->status($this->getCompany())
        );
    }
}
