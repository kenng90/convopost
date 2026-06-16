<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $source->name }} — Book appointment</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-b from-slate-100 to-slate-50 min-h-screen text-slate-900">
<div
    class="max-w-lg mx-auto px-4 py-8 sm:px-6"
    x-data="bookingWidget({
        bookingKey: @js($bookingKey),
        source: @js($source->name),
        companyName: @js($company->name),
        services: @js($services ?? []),
        subdomain: @js($company->subdomain),
        durationOptions: @js($source->durationOptions()),
        timezone: @js($source->timezone),
        showServicePicker: @js($showServicePicker ?? count($services ?? []) > 1),
    })"
    x-cloak
>
    <template x-if="view === 'success'">
        <div class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
            <div class="bg-emerald-50 px-6 py-8 text-center border-b border-emerald-100">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-semibold text-slate-900">Booking confirmed</h1>
                <p class="mt-2 text-sm text-slate-600">We have received your appointment request.</p>
            </div>

            <div class="px-6 py-6 space-y-4">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Service</dt>
                        <dd class="font-medium text-right" x-text="confirmation.service"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Date</dt>
                        <dd class="font-medium text-right" x-text="confirmation.dateLabel"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Time</dt>
                        <dd class="font-medium text-right" x-text="confirmation.timeLabel"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Duration</dt>
                        <dd class="font-medium text-right" x-text="confirmation.durationLabel"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Name</dt>
                        <dd class="font-medium text-right" x-text="confirmation.name"></dd>
                    </div>
                    <div class="flex justify-between gap-4" x-show="confirmation.reference">
                        <dt class="text-slate-500">Reference</dt>
                        <dd class="font-medium text-right" x-text="confirmation.reference"></dd>
                    </div>
                </dl>

                <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    You will receive a confirmation message if reminders are enabled for this service.
                </p>

                <button
                    type="button"
                    @click="startOver()"
                    class="w-full rounded-xl border border-slate-200 bg-white py-3 font-medium text-slate-700 hover:bg-slate-50 transition"
                >
                    Book another appointment
                </button>
            </div>
        </div>
    </template>

    <div x-show="view === 'form'" class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6 space-y-6">
        <div>
            <p class="text-sm text-slate-500" x-text="companyName"></p>
            <h1 class="text-2xl font-semibold text-slate-900" x-text="source || 'Book appointment'"></h1>
            <p class="text-sm text-slate-500 mt-1">Choose a time and enter your details to confirm.</p>
        </div>

        <div x-show="showServicePicker">
            <label class="block text-sm font-medium text-slate-700 mb-2">Service</label>
            <select
                x-model="source"
                @change="onServiceChange()"
                :disabled="loadingDates || loadingSlots || loadingBook"
                class="w-full rounded-xl border-slate-300 disabled:opacity-60"
            >
                <template x-for="service in services" :key="service.id">
                    <option :value="service.name" x-text="service.name"></option>
                </template>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Duration</label>
            <select
                x-model.number="durationMinutes"
                @change="loadDates()"
                :disabled="loadingDates || loadingSlots || loadingBook"
                class="w-full rounded-xl border-slate-300 disabled:opacity-60"
            >
                <template x-for="option in durationOptions" :key="option">
                    <option :value="option" x-text="option + ' minutes'"></option>
                </template>
            </select>
        </div>

        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-slate-700">Date</label>
                <span x-show="loadingDates" class="inline-flex items-center gap-2 text-xs text-violet-600">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Loading dates…
                </span>
            </div>
            <select
                x-model="selectedDate"
                @change="loadSlots()"
                :disabled="loadingDates || loadingSlots || loadingBook || !dates.length"
                class="w-full rounded-xl border-slate-300 disabled:opacity-60"
            >
                <option value="" x-text="loadingDates ? 'Loading available dates…' : (dates.length ? 'Select a date' : 'No dates available')"></option>
                <template x-for="date in dates" :key="date">
                    <option :value="date" x-text="formatDateLabel(date)"></option>
                </template>
            </select>
        </div>

        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-slate-700">Available times</label>
                <span x-show="loadingSlots" class="inline-flex items-center gap-2 text-xs text-violet-600">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Loading times…
                </span>
            </div>

            <div x-show="selectedDate && !loadingSlots && !slots.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500">
                No times available for this date. Please choose another day.
            </div>

            <div x-show="slots.length" class="grid grid-cols-1 gap-2 max-h-64 overflow-y-auto pr-1">
                <template x-for="slot in slots" :key="slot.id">
                    <button
                        type="button"
                        @click="selectSlot(slot)"
                        class="rounded-xl border px-4 py-3 text-left transition"
                        :class="selectedSlot === slot.id ? 'border-violet-600 bg-violet-50 ring-1 ring-violet-600' : 'border-slate-200 hover:border-violet-300 hover:bg-violet-50/40'"
                    >
                        <span class="font-medium text-slate-900" x-text="slot.title"></span>
                    </button>
                </template>
            </div>

            <p x-show="!selectedDate" class="text-xs text-slate-500 mt-2">Select a date to see available times.</p>
        </div>

        <div class="space-y-3 border-t border-slate-100 pt-6">
            <h2 class="text-sm font-semibold text-slate-900">Your details</h2>
            <input
                type="text"
                x-model="name"
                placeholder="Your full name"
                :disabled="loadingBook"
                class="w-full rounded-xl border-slate-300 disabled:opacity-60"
            >
            <input
                type="tel"
                x-model="phone"
                placeholder="Phone number (e.g. +254712345678)"
                :disabled="loadingBook"
                class="w-full rounded-xl border-slate-300 disabled:opacity-60"
            >
        </div>

        <button
            type="button"
            @click="book()"
            :disabled="loadingBook || loadingDates || loadingSlots || !selectedSlot || !name.trim() || !phone.trim() || !source"
            class="w-full rounded-xl bg-violet-600 text-white py-3.5 font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-violet-700 transition inline-flex items-center justify-center gap-2"
        >
            <svg x-show="loadingBook" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span x-text="loadingBook ? 'Confirming your booking…' : 'Confirm booking'"></span>
        </button>

        <div x-show="errorMessage" class="rounded-xl bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700" x-text="errorMessage"></div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function bookingWidget(config) {
    return {
        bookingKey: config.bookingKey,
        source: config.source,
        companyName: config.companyName,
        services: config.services || [],
        subdomain: config.subdomain,
        durationOptions: config.durationOptions,
        durationMinutes: config.durationOptions[0] || 30,
        showServicePicker: config.showServicePicker,
        timezone: config.timezone,
        view: 'form',
        dates: [],
        slots: [],
        selectedDate: '',
        selectedSlot: '',
        selectedSlotMeta: null,
        name: '',
        phone: '',
        loadingDates: false,
        loadingSlots: false,
        loadingBook: false,
        errorMessage: '',
        confirmation: {
            service: '',
            dateLabel: '',
            timeLabel: '',
            durationLabel: '',
            name: '',
            reference: '',
        },

        init() {
            if (this.showServicePicker && this.services.length && !this.source) {
                this.source = this.services[0].name;
                this.durationOptions = this.services[0].duration_options;
                this.durationMinutes = this.durationOptions[0] || 30;
            }
            this.loadDates();
        },

        onServiceChange() {
            const selected = this.services.find(service => service.name === this.source);
            if (selected) {
                this.durationOptions = selected.duration_options;
                this.durationMinutes = this.durationOptions[0] || 30;
            }
            this.loadDates();
        },

        formatDateLabel(isoDate) {
            if (!isoDate) {
                return '';
            }

            const date = new Date(isoDate + 'T12:00:00');
            return date.toLocaleDateString(undefined, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });
        },

        selectSlot(slot) {
            this.selectedSlot = slot.id;
            this.selectedSlotMeta = slot;
            this.errorMessage = '';
        },

        async loadDates() {
            this.loadingDates = true;
            this.errorMessage = '';
            this.selectedDate = '';
            this.selectedSlot = '';
            this.selectedSlotMeta = null;
            this.slots = [];
            this.dates = [];

            try {
                const params = new URLSearchParams({
                    booking_key: this.bookingKey,
                    source: this.source,
                    duration_minutes: this.durationMinutes,
                });

                const response = await fetch('/api/reminders/availability?' + params.toString());
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Could not load available dates.');
                }

                this.dates = data.dates || [];
                if (data.duration_options?.length) {
                    this.durationOptions = data.duration_options;
                }
            } catch (error) {
                this.errorMessage = error.message || 'Could not load available dates.';
            } finally {
                this.loadingDates = false;
            }
        },

        async loadSlots() {
            this.selectedSlot = '';
            this.selectedSlotMeta = null;
            this.slots = [];
            this.errorMessage = '';

            if (!this.selectedDate) {
                return;
            }

            this.loadingSlots = true;

            try {
                const params = new URLSearchParams({
                    booking_key: this.bookingKey,
                    source: this.source,
                    date: this.selectedDate,
                    duration_minutes: this.durationMinutes,
                });

                const response = await fetch('/api/reminders/availability?' + params.toString());
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Could not load available times.');
                }

                this.slots = data.slots || [];
            } catch (error) {
                this.errorMessage = error.message || 'Could not load available times.';
            } finally {
                this.loadingSlots = false;
            }
        },

        async book() {
            this.loadingBook = true;
            this.errorMessage = '';

            try {
                const response = await fetch('/api/reminders/reservation/makeReservation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        booking_key: this.bookingKey,
                        source: this.source,
                        slot_id: this.selectedSlot,
                        name: this.name.trim(),
                        phone: this.phone.trim(),
                    }),
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Booking failed. Please try again.');
                }

                const reservation = data.reservation || {};
                const start = reservation.start_date ? new Date(reservation.start_date) : null;

                this.confirmation = {
                    service: this.source,
                    dateLabel: start
                        ? start.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
                        : this.formatDateLabel(this.selectedDate),
                    timeLabel: this.selectedSlotMeta?.title || (start ? start.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) : ''),
                    durationLabel: `${this.durationMinutes} minutes`,
                    name: this.name.trim(),
                    reference: reservation.external_id || (reservation.id ? `#${reservation.id}` : ''),
                };

                this.view = 'success';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (error) {
                this.errorMessage = error.message || 'Booking failed. Please try again.';
            } finally {
                this.loadingBook = false;
            }
        },

        startOver() {
            this.view = 'form';
            this.name = '';
            this.phone = '';
            this.selectedDate = '';
            this.selectedSlot = '';
            this.selectedSlotMeta = null;
            this.slots = [];
            this.errorMessage = '';
            this.loadDates();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    };
}
</script>
</body>
</html>
