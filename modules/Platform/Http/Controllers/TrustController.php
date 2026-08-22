<?php

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Services\Trust\ConsentService;
use App\Services\Workspace\CsatService;
use Illuminate\View\View;

class TrustController extends Controller
{
    public function index(ConsentService $consent, CsatService $csat): View
    {
        $this->ownerAndStaffOnly();
        $company = $this->getCompany();

        return view('platform::trust.index', [
            'consent' => $consent->summary($company),
            'csat' => $csat->summary($company),
            'audit' => PlatformAuditLog::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->orderByDesc('id')
                ->limit(30)
                ->get(),
            'quality' => $company->getConfig('whatsapp_quality_rating', 'GREEN'),
        ]);
    }
}
