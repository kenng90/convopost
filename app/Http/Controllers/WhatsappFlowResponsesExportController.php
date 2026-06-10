<?php

namespace App\Http\Controllers;

use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowResponse;
use App\Services\WhatsappFlowResponseService;
use Illuminate\Http\Request;

class WhatsappFlowResponsesExportController extends Controller
{
    public function __invoke(Request $request, WhatsappFlowResponseService $service)
    {
        $companyId = $this->activeCompanyId();
        $flowId = $request->integer('flowId');

        $flow = WhatsappFlow::where('company_id', $companyId)->findOrFail($flowId);

        $query = WhatsappFlowResponse::where('company_id', $companyId)
            ->where('whatsapp_flow_id', $flow->id)
            ->when($request->filled('statusFilter'), fn ($q) => $q->where('status', $request->string('statusFilter')))
            ->when($request->filled('dateFrom'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('dateFrom')))
            ->when($request->filled('dateTo'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('dateTo')));

        if ($request->filled('analyticsFieldFilter') && $request->filled('analyticsValueFilter')) {
            $query = $service->applyFieldValueFilter(
                $query,
                $request->string('analyticsFieldFilter'),
                $request->string('analyticsValueFilter'),
                $flow
            );
        }

        return $service->exportCsv($query, $flow);
    }
}
