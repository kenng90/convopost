<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Api\PublicApiResponse;
use App\Services\InvoiceWhatsAppService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Invoice\Models\Invoice;

class InvoicesController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_phone' => 'required|string|max:20',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|size:3',
            'description' => 'nullable|string',
            'items' => 'nullable|array',
            'send_whatsapp' => 'sometimes|boolean',
        ]);

        $company = $request->attributes->get('public_api_company');

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => Invoice::generateInvoiceNumber($company),
            'customer_name' => $validated['customer_name'] ?? 'Guest',
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'] ?? null,
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency'] ?? ($company->currency ?: 'KES')),
            'status' => 'draft',
            'description' => $validated['description'] ?? null,
            'items' => $validated['items'] ?? [],
            'notes' => ['source' => Invoice::SOURCE_INVOICE],
        ]);

        $whatsappSent = false;

        if ($request->boolean('send_whatsapp', true)) {
            $whatsappSent = (new InvoiceWhatsAppService($company))->sendInvoice($invoice);
            if ($whatsappSent) {
                $invoice->markAsSent();
            }
        }

        return PublicApiResponse::success([
            'invoice' => $invoice->fresh()->toInvoiceArray(),
            'whatsapp_sent' => $whatsappSent,
            'pay_url' => url('/catalog/pay/'.$invoice->id),
        ], 201);
    }

    public function show(Request $request, string $invoice): JsonResponse
    {
        $company = $request->attributes->get('public_api_company');
        $model = Invoice::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($invoice) {
                $query->where('public_uuid', $invoice);
                if (ctype_digit($invoice)) {
                    $query->orWhere('id', (int) $invoice);
                }
            })
            ->first();

        if (! $model) {
            throw new HttpResponseException(PublicApiResponse::error('not_found', 'Invoice not found', 404));
        }

        return PublicApiResponse::success($model->toInvoiceArray());
    }
}
