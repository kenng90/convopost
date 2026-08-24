<?php

namespace App\Services\Collections;

use App\Enums\CollectionStatus;
use App\Jobs\Collections\AdvanceCollectionChase;
use App\Jobs\Collections\RequestCollectionPayment;
use App\Jobs\Collections\WatchStkTimeout;
use App\Models\Company;
use App\Services\Billing\CreditCharger;
use App\Services\InvoiceWhatsAppService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\PlanEntitlementResolver;
use Illuminate\Support\Facades\Log;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;

class CollectionEngine
{
    public function __construct(
        protected CollectionPolicy $policy,
        protected CollectionFulfillment $fulfillment,
        protected PaymentGatewayManager $gateways,
    ) {
    }

    /**
     * @return array{success: bool, payment?: InvoicePayment|null, message?: string, authorization_url?: string|null}
     */
    public function start(Invoice $invoice, ?string $channel = null): array
    {
        $invoice->refresh();

        if ($this->isTerminal($invoice)) {
            return ['success' => false, 'message' => 'Invoice is already closed'];
        }

        if ($this->hasInFlightPayment($invoice)) {
            return ['success' => false, 'message' => 'A payment attempt is already in flight'];
        }

        $this->applyStatus($invoice, CollectionStatus::Due, [
            'due_at' => $invoice->due_at ?? now(),
            'chase_step' => 0,
        ]);

        return $this->initiate($invoice, $channel);
    }

    /**
     * @return array{success: bool, payment?: InvoicePayment|null, message?: string, authorization_url?: string|null}
     */
    public function initiate(Invoice $invoice, ?string $channel = null): array
    {
        $company = $invoice->company ?? Company::find($invoice->company_id);
        if (! $company) {
            return ['success' => false, 'message' => 'Company not found'];
        }

        $requested = $channel ?: $this->gateways->preferredForCompany($company)?->key();

        if ($requested === 'mpesa') {
            $charger = app(CreditCharger::class);
            if (! $charger->canCharge($company, 'mpesa_stk_push')) {
                return ['success' => false, 'message' => $charger->insufficientCreditsMessage('mpesa_stk_push')];
            }
        }

        $gateway = $requested ? $this->gateways->get($requested) : $this->gateways->preferredForCompany($company);
        if (! $gateway || ! $gateway->isConfigured($company)) {
            $gateway = $this->gateways->preferredForCompany($company);
        }

        if (! $gateway || ! $gateway->isConfigured($company)) {
            return ['success' => false, 'message' => 'No payment method is configured'];
        }

        $result = $gateway->initiate($company, $invoice, [
            'amount' => $invoice->getRemainingAmount(),
            'phone' => $invoice->customer_phone,
            'email' => $invoice->customer_email,
            'account_reference' => 'INV-'.mb_substr((string) $invoice->invoice_number, 0, 8),
            'description' => 'Invoice '.$invoice->invoice_number,
        ]);

        if (! ($result['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Unable to start payment',
            ];
        }

        if ($gateway->key() === 'mpesa') {
            app(CreditCharger::class)->charge($company, 'mpesa_stk_push');
        }

        /** @var InvoicePayment|null $payment */
        $payment = $result['payment'] ?? null;
        if ($payment) {
            $this->trackInitiated($invoice, $payment);
        }

        return [
            'success' => true,
            'payment' => $payment,
            'authorization_url' => $result['authorization_url'] ?? null,
            'message' => $result['message'] ?? 'Payment initiated',
        ];
    }

    public function trackInitiated(Invoice $invoice, InvoicePayment $payment): void
    {
        $channel = $payment->payment_method ?: $payment->paid_via;
        $status = $channel === 'mpesa'
            ? CollectionStatus::PendingPin
            : CollectionStatus::Requested;

        $this->applyStatus($invoice, $status, [
            'collection_channel' => $channel,
            'next_chase_at' => null,
        ]);

        if ($channel === 'mpesa' && $payment->status === 'pending') {
            WatchStkTimeout::dispatch($payment->id)
                ->delay(now()->addSeconds($this->policy->stkTimeoutSeconds()));
        }
    }

    public function onPaymentSucceeded(InvoicePayment $payment): void
    {
        $invoice = $payment->invoice;
        if (! $invoice) {
            return;
        }

        if ($invoice->getTotalPaidAmount() + 0.009 < (float) $invoice->amount) {
            $this->applyStatus($invoice, CollectionStatus::Partial, [
                'next_chase_at' => now()->addMinutes(5),
                'chase_step' => max(2, (int) $invoice->chase_step),
            ]);

            return;
        }

        $this->applyStatus($invoice, CollectionStatus::Paid, [
            'next_chase_at' => null,
        ]);

        $this->fulfillment->onPaid($invoice, $payment);
    }

    public function onPaymentFailed(InvoicePayment $payment): void
    {
        $invoice = $payment->invoice;
        if (! $invoice || $this->isTerminal($invoice)) {
            return;
        }

        if (! $this->collectionsEnabled($invoice)) {
            return;
        }

        $stkAttempts = $invoice->payments()
            ->where(function ($query) {
                $query->where('payment_method', 'mpesa')->orWhere('paid_via', 'mpesa');
            })
            ->count();

        $isMpesa = in_array($payment->payment_method, ['mpesa', null], true)
            || $payment->paid_via === 'mpesa'
            || filled($payment->mpesa_checkout_request_id);

        if ($isMpesa && $stkAttempts < $this->policy->maxStkAttempts()) {
            $this->applyStatus($invoice, CollectionStatus::Failed, [
                'chase_step' => 1,
                'next_chase_at' => now()->addMinutes($this->policy->stkRetryDelayMinutes()),
            ]);

            RequestCollectionPayment::dispatch($invoice->id, 'mpesa')
                ->delay(now()->addMinutes($this->policy->stkRetryDelayMinutes()));

            return;
        }

        $hasPaystack = $invoice->payments()
            ->where(function ($query) {
                $query->where('payment_method', 'paystack')->orWhere('paid_via', 'paystack');
            })
            ->exists();

        $company = $invoice->company;
        $paystack = $company ? $this->gateways->get('paystack') : null;
        if (! $hasPaystack && $paystack && $company && $paystack->isConfigured($company)) {
            $this->applyStatus($invoice, CollectionStatus::Chasing, [
                'chase_step' => 2,
                'collection_channel' => 'paystack',
                'next_chase_at' => now()->addHours($this->policy->firstReminderHours()),
            ]);

            RequestCollectionPayment::dispatch($invoice->id, 'paystack');

            return;
        }

        if ((int) $invoice->chase_step < 3) {
            $this->applyStatus($invoice, CollectionStatus::Chasing, [
                'chase_step' => 3,
                'next_chase_at' => now()->addHours($this->policy->firstReminderHours()),
            ]);
            AdvanceCollectionChase::dispatch($invoice->id)
                ->delay(now()->addHours($this->policy->firstReminderHours()));

            return;
        }

        $this->fulfillment->onTerminalFailure(
            $invoice,
            $payment,
            (string) ($payment->result_description ?? 'Payment failed')
        );
    }

    public function timeoutPendingPayment(InvoicePayment $payment): void
    {
        $payment->refresh();
        if ($payment->status !== 'pending') {
            return;
        }

        $payment->markAsFailed('STK wait timed out');
    }

    public function advance(Invoice $invoice): void
    {
        $invoice->refresh();
        if ($this->isTerminal($invoice) || ! $this->collectionsEnabled($invoice)) {
            return;
        }

        if ($invoice->next_chase_at && $invoice->next_chase_at->isFuture()) {
            return;
        }

        $step = (int) $invoice->chase_step;

        if ($step <= 1 && ! $this->hasInFlightPayment($invoice)) {
            $this->initiate($invoice, 'mpesa');

            return;
        }

        if ($step === 2 && ! $this->hasInFlightPayment($invoice)) {
            $result = $this->initiate($invoice, 'paystack');
            $this->sendChase($invoice, $this->paystackChaseMessage($invoice, $result['authorization_url'] ?? null));
            $this->applyStatus($invoice, CollectionStatus::Chasing, [
                'chase_step' => 3,
                'next_chase_at' => now()->addHours($this->policy->firstReminderHours()),
            ]);

            return;
        }

        if ($step === 3) {
            $this->sendChase($invoice, $this->reminderMessage($invoice, 1));
            $this->applyStatus($invoice, CollectionStatus::Chasing, [
                'chase_step' => 4,
                'next_chase_at' => now()->addHours(
                    max(1, $this->policy->secondReminderHours() - $this->policy->firstReminderHours())
                ),
            ]);

            return;
        }

        if ($step >= 4) {
            $this->sendChase($invoice, $this->reminderMessage($invoice, 2));
            $latest = $invoice->payments()->latest('id')->first();
            if ($latest) {
                $this->fulfillment->onTerminalFailure($invoice, $latest, 'Unpaid after collection chase');
            }
        }
    }

    public function markUnmatched(Invoice $invoice): void
    {
        $this->applyStatus($invoice, CollectionStatus::Unmatched);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function applyStatus(Invoice $invoice, CollectionStatus $status, array $attributes = []): void
    {
        $invoice->forceFill(array_merge([
            'collection_status' => $status->value,
        ], $attributes))->save();
    }

    public function collectionsEnabled(Invoice $invoice): bool
    {
        $company = $invoice->company ?? Company::find($invoice->company_id);
        if (! $company) {
            return false;
        }

        if ((string) $company->getConfig('collections_enabled', 'yes') === 'no') {
            return false;
        }

        $owner = $company->user;
        if (! $owner) {
            return false;
        }

        try {
            $resolver = app(PlanEntitlementResolver::class);

            return $resolver->userHasCapability($owner, 'collections')
                || $resolver->userHasCapability($owner, 'payments');
        } catch (\Throwable $e) {
            Log::debug('Collection entitlement check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function shouldDeferFlowFailure(Invoice $invoice): bool
    {
        return $this->collectionsEnabled($invoice) && ! $this->isTerminal($invoice);
    }

    public function isTerminal(Invoice $invoice): bool
    {
        $status = CollectionStatus::tryFrom((string) $invoice->collection_status);

        return $status?->isTerminal()
            ?? in_array($invoice->status, ['paid', 'cancelled'], true);
    }

    protected function hasInFlightPayment(Invoice $invoice): bool
    {
        return $invoice->payments()->where('status', 'pending')->exists();
    }

    protected function sendChase(Invoice $invoice, string $message): void
    {
        $company = $invoice->company ?? Company::find($invoice->company_id);
        if (! $company) {
            return;
        }

        try {
            $service = new InvoiceWhatsAppService($company);
            $service->sendChaseMessage($invoice, $message);
        } catch (\Throwable $e) {
            Log::info('Collection chase WhatsApp skipped', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function paystackChaseMessage(Invoice $invoice, ?string $url): string
    {
        $link = $url ?: rtrim((string) config('app.url'), '/').'/catalog/pay/'.($invoice->public_uuid ?? $invoice->id);

        return __('M-Pesa prompt expired. Pay :amount :currency here: :link', [
            'amount' => number_format((float) $invoice->getRemainingAmount(), 2),
            'currency' => $invoice->currency ?: 'KES',
            'link' => $link,
        ]);
    }

    protected function reminderMessage(Invoice $invoice, int $n): string
    {
        return __('Reminder :n: invoice :number still has a balance of :amount :currency.', [
            'n' => $n,
            'number' => $invoice->invoice_number,
            'amount' => number_format((float) $invoice->getRemainingAmount(), 2),
            'currency' => $invoice->currency ?: 'KES',
        ]);
    }
}
