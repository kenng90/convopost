<?php

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Platform\HealthMonitorService;
use Illuminate\Http\JsonResponse;

class HealthMonitorController extends Controller
{
    public function __construct(private HealthMonitorService $health)
    {
    }

    public function index(): JsonResponse
    {
        $this->ownerAndStaffOnly();
        $company = $this->getCompany();

        return response()->json([
            'alerts' => $this->health->alerts($company),
            'count' => $this->health->healthyCount($company),
        ]);
    }
}
