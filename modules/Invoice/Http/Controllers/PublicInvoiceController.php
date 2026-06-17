<?php

namespace Modules\Invoice\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Invoice\Models\Invoice;

class PublicInvoiceController extends Controller
{
    public function show(Invoice $invoice): View
    {
        $invoice->load(['company', 'payments']);

        return view('invoice::public.show', [
            'invoice' => $invoice,
            'company' => $invoice->company,
            'paymentUrl' => route('catalog.invoice.pay', $invoice->public_uuid),
        ]);
    }
}
