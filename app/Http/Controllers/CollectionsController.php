<?php

namespace App\Http\Controllers;

use App\Enums\CollectionStatus;
use App\Services\Collections\CollectionEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;

class CollectionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('plan.capability:collections');
    }

    public function index(Request $request): View
    {
        $company = $this->selectedCompany($request);
        $accessibleCompanies = auth()->user()->accessibleCompanies();

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:32'],
            'source' => ['nullable', 'string', 'max:32'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Invoice::query()
            ->where('company_id', $company->id)
            ->with(['payments' => fn ($payments) => $payments->latest('id')])
            ->orderByRaw("case when collection_status in ('unmatched','failed','chasing','pending_pin') then 0 else 1 end")
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('collection_status', $validated['status']);
        } else {
            $query->whereIn('collection_status', CollectionStatus::open());
        }

        if (! empty($validated['source'])) {
            $query->where('notes->source', $validated['source']);
        }

        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where(function ($inner) use ($search) {
                $inner->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('customer_phone', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%');
            });
        }

        return view('collections.index', [
            'company' => $company,
            'accessibleCompanies' => $accessibleCompanies,
            'currentCompanyId' => $company->id,
            'invoices' => $query->paginate(30)->withQueryString(),
            'filters' => [
                'status' => $validated['status'] ?? '',
                'source' => $validated['source'] ?? '',
                'q' => $validated['q'] ?? '',
            ],
            'openCount' => Invoice::query()
                ->where('company_id', $company->id)
                ->whereIn('collection_status', CollectionStatus::open())
                ->count(),
        ]);
    }

    public function match(Request $request, int $invoice, CollectionEngine $engine): RedirectResponse
    {
        $company = $this->selectedCompany($request);
        $invoice = Invoice::query()->where('company_id', $company->id)->findOrFail($invoice);

        $validated = $request->validate([
            'receipt_number' => ['required', 'string', 'max:64'],
            'amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        $payment = $invoice->payments()->latest('id')->first();
        if (! $payment) {
            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'payment_method' => 'mpesa',
                'paid_via' => 'mpesa',
                'amount' => (float) ($validated['amount'] ?? $invoice->amount),
                'status' => 'pending',
            ]);
        }

        $payment->markAsSuccess($validated['receipt_number']);

        return back()->with('success', __('Payment matched to invoice :number', [
            'number' => $invoice->invoice_number,
        ]));
    }

    public function retry(Request $request, int $invoice, CollectionEngine $engine): RedirectResponse
    {
        $company = $this->selectedCompany($request);
        $invoice = Invoice::query()->where('company_id', $company->id)->findOrFail($invoice);

        $result = $engine->start($invoice);

        return back()->with(
            ($result['success'] ?? false) ? 'success' : 'error',
            $result['message'] ?? __('Collection retry queued')
        );
    }

    protected function selectedCompany(Request $request)
    {
        $user = auth()->user();
        $accessibleCompanies = $user->accessibleCompanies();
        $companyId = $request->input('company_id', $user->company->id);
        $company = $accessibleCompanies->where('id', $companyId)->first();

        return $company ?: $user->company;
    }
}
