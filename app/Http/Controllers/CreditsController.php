<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\GrantCreditsRequest;
use App\Http\Requests\UpdateCreditCostsRequest;
use App\Models\Company;
use App\Models\Cost;
use App\Services\Billing\AdminCreditGrantService;
use App\Services\Billing\CreditCostService;
use App\Services\Billing\SyncCreditActions;
use App\Services\Platform\ManagedAiService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CreditsController extends Controller
{
    public function index(CreditCostService $costs, SyncCreditActions $sync)
    {
        $this->adminOnly();

        $sync->sync(onlyMissing: true);

        $actions = $costs->actionsForAdmin();
        $categories = config('credit-actions.category_labels', []);

        return view('credits.costs.costs', compact('actions', 'categories'));
    }

    public function create(): View
    {
        $this->adminOnly();

        $companies = Company::query()
            ->with('user')
            ->orderBy('name')
            ->get();

        $selectedCompanyId = (int) request('company_id');
        $selectedCompany = $selectedCompanyId > 0
            ? $companies->firstWhere('id', $selectedCompanyId)
            : null;

        $walletSummary = null;
        if ($selectedCompany?->user) {
            $walletSummary = $selectedCompany->buildCreditWalletsSummary();
            $aiStatus = app(ManagedAiService::class)->status($selectedCompany);
            $walletSummary = [
                'messaging_available' => $selectedCompany->user->getTotalRemainingCredits(),
                'ai_remaining' => $aiStatus['remaining'] ?? 0,
                'ai_allowance' => $aiStatus['monthly_allowance'] ?? 0,
                'ai_bonus' => $aiStatus['bonus_credits'] ?? 0,
                'wallets' => $walletSummary,
            ];
        }

        return view('credits.grant', [
            'companies' => $companies,
            'selectedCompanyId' => $selectedCompanyId,
            'walletSummary' => $walletSummary,
            'defaultExpiry' => now()->addDays(30)->toDateString(),
        ]);
    }

    public function store(GrantCreditsRequest $request, AdminCreditGrantService $grants): RedirectResponse
    {
        $this->adminOnly();

        $company = Company::with('user')->findOrFail($request->integer('company_id'));

        $result = $grants->grant(
            company: $company,
            admin: $request->user(),
            messagingCredits: (float) $request->input('messaging_credits', 0),
            aiCredits: (int) $request->input('ai_credits', 0),
            messagingExpiresAt: $request->filled('messaging_expires_at')
                ? Carbon::parse($request->input('messaging_expires_at'))->endOfDay()
                : null,
            note: $request->input('note'),
        );

        $parts = [];
        if ($result['messaging_granted'] > 0) {
            $parts[] = __(':amount messaging credits', ['amount' => number_format($result['messaging_granted'])]);
        }
        if ($result['ai_granted'] > 0) {
            $parts[] = __(':amount AI credits', ['amount' => number_format($result['ai_granted'])]);
        }

        return redirect()
            ->route('credits.create', ['company_id' => $company->id])
            ->withStatus(__('Granted :details to :company.', [
                'details' => implode(' + ', $parts),
                'company' => $company->name,
            ]));
    }

    public function updateCosts(UpdateCreditCostsRequest $request, CreditCostService $costs): RedirectResponse
    {
        $this->adminOnly();

        foreach ($request->validated('costs') as $action => $values) {
            $storedCost = ($values['type'] ?? '1') === '-1'
                ? -1
                : (int) $values['cost'];

            Cost::query()->updateOrCreate(
                ['action' => $action],
                ['cost' => $storedCost],
            );
        }

        $costs->flushCache();

        return redirect()
            ->route('credits.index')
            ->withStatus(__('Credit costs updated successfully.'));
    }
}
