<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $company->name }} — {{ $occurrence['starts_at_label'] ?? __('Event') }}</title>
    @include('layouts.favicon')
    <script src="https://cdn.tailwindcss.com"></script>
    @include('reminders::booking.partials.phone-input-head')
</head>
<body class="bg-slate-50 min-h-screen">
<div
    class="max-w-lg mx-auto p-6"
    x-data="eventRegister({
        bookingKey: @js($bookingKey),
        occurrenceId: @js($occurrence['id']),
        eventTitle: @js($event['title']),
        startsAtLabel: @js($occurrence['starts_at_label']),
        seatsRemaining: @js($occurrence['seats_remaining']),
        initialPhoneCountry: @js($bookingPhoneCountry ?? 'ke'),
        paymentRequired: @js($paymentConfig['payment_required'] ?? false),
        paymentAmount: @js($paymentConfig['payment_amount']),
        paymentTotalAmount: @js($paymentConfig['payment_total_amount']),
        paymentUpfrontPercent: @js($paymentConfig['payment_upfront_percent'] ?? 100),
        paymentCurrency: @js($paymentConfig['payment_currency'] ?? 'KES'),
        mpesaConfigured: @js($mpesaConfigured ?? false),
    })"
>
    <div class="bg-white rounded-2xl shadow-lg p-6 space-y-6" x-show="view === 'form'">
        <div>
            <a href="{{ route('reminders.booking.events', ['subdomain' => $company->subdomain]) }}" class="text-sm text-violet-600 hover:underline">{{ __('← All events') }}</a>
            <h1 class="text-2xl font-semibold text-slate-900 mt-2" x-text="eventTitle"></h1>
            <p class="text-sm text-slate-500 mt-1" x-text="startsAtLabel"></p>
            <p class="text-xs text-slate-500 mt-1" x-text="`${seatsRemaining} {{ __('seats remaining') }}`"></p>
        </div>

        <form @submit.prevent="register" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Full name') }}</label>
                <input type="text" x-model="name" required class="w-full rounded-xl border border-slate-200 px-4 py-2.5">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Phone') }}</label>
                <input type="tel" id="event-booking-phone-input" required class="w-full rounded-xl border border-slate-200 px-4 py-2.5">
                <p class="text-xs text-slate-500 mt-1">{{ __('Select your country code, then enter your number without the leading 0.') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Party size') }}</label>
                <input type="number" x-model.number="partySize" min="1" :max="seatsRemaining" class="w-full rounded-xl border border-slate-200 px-4 py-2.5">
            </div>
            <p x-show="errorMessage" class="text-sm text-red-600" x-text="errorMessage"></p>
            <div x-show="paymentRequired" class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-900">
                <p class="font-medium">{{ __('Payment required') }}</p>
                <p class="mt-1" x-text="paymentSummary()"></p>
                <p x-show="!mpesaConfigured" class="mt-2 text-red-700">{{ __('Online payment is not available right now. Please contact the business.') }}</p>
            </div>
            <button type="submit" :disabled="loading || (paymentRequired && !mpesaConfigured)" class="w-full rounded-xl bg-violet-600 text-white py-3 font-medium hover:bg-violet-700 disabled:opacity-50">
                <span x-show="!loading" x-text="confirmButtonLabel()"></span>
                <span x-show="loading">{{ __('Processing...') }}</span>
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-lg p-6 space-y-6 text-center" x-show="view === 'paying'" x-cloak>
        <div class="text-4xl">📱</div>
        <h2 class="text-xl font-semibold text-slate-900">{{ __('Complete payment on your phone') }}</h2>
        <p class="text-sm text-slate-600">{{ __('We sent an M-Pesa prompt to your phone. Enter your PIN to confirm.') }}</p>
        <p class="text-sm text-slate-500">{{ __('Waiting for payment confirmation...') }}</p>
    </div>

    <div class="bg-white rounded-2xl shadow-lg p-6 space-y-4 text-center" x-show="view === 'success'" x-cloak>
        <div class="text-4xl">✓</div>
        <h2 class="text-xl font-semibold text-slate-900">{{ __('You are registered!') }}</h2>
        <p class="text-sm text-slate-600" x-text="confirmationLine"></p>
        <a href="{{ route('reminders.booking.events', ['subdomain' => $company->subdomain]) }}" class="inline-block text-sm text-violet-600 hover:underline">{{ __('Browse more events') }}</a>
    </div>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@include('reminders::booking.partials.phone-input-script')
@include('reminders::booking.partials.payment-summary-script')
<script>
function eventRegister(config) {
    return {
        bookingKey: config.bookingKey,
        occurrenceId: config.occurrenceId,
        eventTitle: config.eventTitle,
        startsAtLabel: config.startsAtLabel,
        seatsRemaining: config.seatsRemaining,
        initialPhoneCountry: config.initialPhoneCountry || 'ke',
        paymentRequired: config.paymentRequired || false,
        paymentAmount: config.paymentAmount,
        paymentTotalAmount: config.paymentTotalAmount,
        paymentUpfrontPercent: config.paymentUpfrontPercent || 100,
        paymentCurrency: config.paymentCurrency || 'KES',
        mpesaConfigured: config.mpesaConfigured !== false,
        name: '',
        iti: null,
        partySize: 1,
        view: 'form',
        loading: false,
        errorMessage: '',
        confirmationLine: '',
        init() {
            this.$nextTick(() => {
                this.iti = window.BookingPhone.init('event-booking-phone-input', this.initialPhoneCountry);
            });
        },
        paymentSummary() {
            if (!this.paymentRequired || !this.paymentAmount) {
                return '';
            }

            return window.BookingPayment.summary(
                this.paymentCurrency,
                this.paymentAmount,
                this.paymentTotalAmount,
                this.paymentUpfrontPercent
            );
        },
        formatAmount(amount) {
            return window.BookingPayment.formatAmount(amount);
        },
        confirmButtonLabel() {
            if (this.paymentRequired && this.paymentAmount) {
                return window.BookingPayment.buttonLabel(
                    this.paymentCurrency,
                    this.paymentAmount,
                    '{{ __('register') }}'
                );
            }

            return '{{ __('Confirm registration') }}';
        },
        showSuccess(registration) {
            this.confirmationLine = `${this.eventTitle} — ${this.startsAtLabel}`;
            this.view = 'success';
        },
        async pollPayment(invoicePublicUuid) {
            const maxAttempts = 45;

            for (let attempt = 0; attempt < maxAttempts; attempt++) {
                await new Promise(resolve => setTimeout(resolve, 2000));

                const params = new URLSearchParams({ booking_key: this.bookingKey });
                const response = await fetch(`/api/reminders/booking/payment/${invoicePublicUuid}?` + params.toString());
                const data = await response.json();

                if (!response.ok) {
                    continue;
                }

                const payment = data.payment || {};
                if (payment.status === 'success' && payment.fulfilled) {
                    this.showSuccess(payment.registration);
                    return;
                }

                if (payment.status === 'failed') {
                    throw new Error('{{ __('Payment failed or was cancelled. Please try again.') }}');
                }
            }

            throw new Error('{{ __('Payment is taking longer than expected. If you completed M-Pesa, contact the business with your receipt.') }}');
        },
        async register() {
            this.loading = true;
            this.errorMessage = '';

            if (this.paymentRequired && !this.mpesaConfigured) {
                this.errorMessage = '{{ __('Online payment is not available right now.') }}';
                this.loading = false;
                return;
            }

            const phone = window.BookingPhone.digits(this.iti);
            if (! phone || ! window.BookingPhone.isValid(this.iti)) {
                this.errorMessage = '{{ __('Please enter a valid phone number.') }}';
                this.loading = false;
                return;
            }

            try {
                const response = await fetch('/api/reminders/booking/pay/event', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        booking_key: this.bookingKey,
                        occurrence_id: this.occurrenceId,
                        name: this.name.trim(),
                        phone: phone,
                        party_size: this.partySize,
                    }),
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || '{{ __('Registration failed.') }}');
                }

                if (!data.requires_action) {
                    this.showSuccess(data.registration);
                    return;
                }

                this.view = 'paying';
                await this.pollPayment(data.invoice_public_uuid);
            } catch (error) {
                this.errorMessage = error.message || '{{ __('Registration failed.') }}';
                if (this.view === 'paying') {
                    this.view = 'form';
                }
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
</body>
</html>
