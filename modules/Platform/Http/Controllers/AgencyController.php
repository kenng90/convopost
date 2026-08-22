<?php

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Agency\AgencyPortfolioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgencyController extends Controller
{
    public function index(AgencyPortfolioService $portfolio): View
    {
        $this->ownerOnly();
        $owner = auth()->user();

        return view('platform::agency.index', [
            'companies' => $portfolio->companies($owner),
            'rollup' => $portfolio->rollup($owner),
            'playbooks' => array_keys(config('outcome-playbooks.playbooks', [])),
        ]);
    }

    public function clonePlaybook(Request $request, AgencyPortfolioService $portfolio): RedirectResponse
    {
        $this->ownerOnly();

        $validated = $request->validate([
            'from_company_id' => 'required|integer',
            'to_company_id' => 'required|integer|different:from_company_id',
            'playbook' => 'required|string|in:'.implode(',', array_keys(config('outcome-playbooks.playbooks', []))),
        ]);

        $from = Company::findOrFail($validated['from_company_id']);
        $to = Company::findOrFail($validated['to_company_id']);

        $result = $portfolio->clonePlaybook(auth()->user(), $from, $to, $validated['playbook']);

        if (! ($result['success'] ?? false)) {
            return redirect()->route('agency.index')->withError($result['message']);
        }

        return redirect()->route('agency.index')->withStatus($result['message']);
    }
}
