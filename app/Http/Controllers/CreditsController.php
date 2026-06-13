<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCreditCostsRequest;
use App\Models\Cost;
use App\Services\Billing\CreditCostService;
use App\Services\Billing\SyncCreditActions;
use Illuminate\Http\RedirectResponse;

class CreditsController extends Controller
{
    public function index(CreditCostService $costs, SyncCreditActions $sync)
    {
        $sync->sync(onlyMissing: true);

        $actions = $costs->actionsForAdmin();
        $categories = config('credit-actions.category_labels', []);

        return view('credits.costs.costs', compact('actions', 'categories'));
    }

    public function updateCosts(UpdateCreditCostsRequest $request, CreditCostService $costs): RedirectResponse
    {
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
