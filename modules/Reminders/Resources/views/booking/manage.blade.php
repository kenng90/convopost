<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $company->name }} — {{ __('Manage booking') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
<div
    class="max-w-lg mx-auto p-6"
    x-data="bookingManage({
        subdomain: @js($company->subdomain),
        companyName: @js($company->name),
        bookingKey: @js($bookingKey),
        eventsEnabled: @js($eventsEnabled ?? false),
        initialType: @js($initialType ?? 'appointments'),
        tokenPayload: @js($tokenPayload),
        record: @js($record),
    })"
    x-cloak
>
    <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6 space-y-6">
        <div>
            <p class="text-sm text-slate-500" x-text="companyName"></p>
            <h1 class="text-2xl font-semibold text-slate-900">{{ __('Manage booking') }}</h1>
            <p class="text-sm text-slate-500 mt-1">{{ __('Look up a booking to cancel or reschedule online.') }}</p>
        </div>

        <template x-if="!record">
            <div class="space-y-4">
                <div class="flex gap-2" x-show="eventsEnabled">
                    <button type="button" @click="type = 'appointments'" :class="type === 'appointments' ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-700'" class="flex-1 rounded-xl py-2 text-sm font-medium">{{ __('Appointments') }}</button>
                    <button type="button" @click="type = 'events'" :class="type === 'events' ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-700'" class="flex-1 rounded-xl py-2 text-sm font-medium">{{ __('Events') }}</button>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Booking reference') }}</label>
                    <input x-model="reference" type="text" placeholder="#123" class="w-full rounded-xl border-slate-300" />
                    <p class="text-xs text-slate-500 mt-1">{{ __('Use the number from your confirmation (for example #42).') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Name or last 4 phone digits') }}</label>
                    <input x-model="verify" type="text" class="w-full rounded-xl border-slate-300" />
                </div>

                <p class="text-sm text-red-600" x-show="error" x-text="error"></p>

                <button type="button" @click="lookup()" :disabled="loading" class="w-full rounded-xl bg-violet-600 py-3 font-medium text-white hover:bg-violet-700 disabled:opacity-60">
                    <span x-show="!loading">{{ __('Find booking') }}</span>
                    <span x-show="loading">{{ __('Searching…') }}</span>
                </button>

                <p class="text-center text-sm">
                    <a :href="`/book/${subdomain}`" class="text-violet-600 hover:underline">{{ __('Book a new appointment') }}</a>
                </p>
            </div>
        </template>

        <template x-if="record">
            <div class="space-y-5">
                <div class="rounded-xl bg-slate-50 p-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><span class="text-slate-500">{{ __('Reference') }}</span><span class="font-medium" x-text="record.reference"></span></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">{{ __('Status') }}</span><span class="font-medium" x-text="record.status_label"></span></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">{{ __('Service / event') }}</span><span class="font-medium text-right" x-text="record.service"></span></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">{{ __('Date') }}</span><span class="font-medium" x-text="record.date_label"></span></div>
                    <div class="flex justify-between gap-3"><span class="text-slate-500">{{ __('Time') }}</span><span class="font-medium" x-text="record.time_label"></span></div>
                    <div class="flex justify-between gap-3" x-show="record.name"><span class="text-slate-500">{{ __('Name') }}</span><span class="font-medium" x-text="record.name"></span></div>
                </div>

                <p class="text-sm text-emerald-700" x-show="successMessage" x-text="successMessage"></p>
                <p class="text-sm text-red-600" x-show="error" x-text="error"></p>

                <div class="space-y-3" x-show="view === 'detail'">
                    <button type="button" x-show="record.can_reschedule" @click="startReschedule()" class="w-full rounded-xl bg-violet-600 py-3 font-medium text-white hover:bg-violet-700">{{ __('Reschedule') }}</button>
                    <button type="button" x-show="record.can_cancel" @click="cancelBooking()" :disabled="loading" class="w-full rounded-xl border border-red-200 bg-red-50 py-3 font-medium text-red-700 hover:bg-red-100 disabled:opacity-60">{{ __('Cancel booking') }}</button>
                    <a :href="`/book/${subdomain}/manage`" class="block text-center text-sm text-slate-500 hover:underline">{{ __('Look up another booking') }}</a>
                </div>

                <div class="space-y-4" x-show="view === 'reschedule'">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Date') }}</label>
                        <select x-model="selectedDate" @change="loadSlots()" class="w-full rounded-xl border-slate-300" :disabled="loadingDates || loadingSlots">
                            <option value="">{{ __('Select a date') }}</option>
                            <template x-for="date in dates" :key="date">
                                <option :value="date" x-text="date"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Time') }}</label>
                        <select x-model="selectedSlotId" class="w-full rounded-xl border-slate-300" :disabled="loadingSlots || !slots.length">
                            <option value="">{{ __('Select a time') }}</option>
                            <template x-for="slot in slots" :key="slot.id">
                                <option :value="slot.id" x-text="slot.title"></option>
                            </template>
                        </select>
                    </div>
                    <button type="button" @click="confirmReschedule()" :disabled="loading || !selectedSlotId" class="w-full rounded-xl bg-violet-600 py-3 font-medium text-white hover:bg-violet-700 disabled:opacity-60">{{ __('Confirm new time') }}</button>
                    <button type="button" @click="view = 'detail'; error = ''" class="w-full rounded-xl border border-slate-200 py-3 font-medium text-slate-700">{{ __('Back') }}</button>
                </div>
            </div>
        </template>
    </div>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function bookingManage(config) {
    return {
        subdomain: config.subdomain,
        companyName: config.companyName,
        bookingKey: config.bookingKey,
        eventsEnabled: config.eventsEnabled,
        type: config.initialType || 'appointments',
        tokenPayload: config.tokenPayload,
        record: config.record,
        reference: '',
        verify: '',
        error: '',
        successMessage: '',
        loading: false,
        loadingDates: false,
        loadingSlots: false,
        view: config.record ? 'detail' : 'lookup',
        dates: [],
        slots: [],
        selectedDate: '',
        selectedSlotId: '',

        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        async lookup() {
            this.error = '';
            this.loading = true;
            try {
                const response = await fetch(`/book/${this.subdomain}/manage/lookup`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                    },
                    body: JSON.stringify({
                        type: this.type,
                        reference: this.reference,
                        verify: this.verify,
                    }),
                });
                const data = await response.json();
                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || 'Lookup failed');
                }
                window.location.href = data.redirect;
            } catch (e) {
                this.error = e.message || 'Lookup failed';
            } finally {
                this.loading = false;
            }
        },

        async cancelBooking() {
            if (!confirm(@js(__('Cancel this booking?')))) return;
            this.error = '';
            this.loading = true;
            try {
                const path = this.record.type === 'event'
                    ? `/book/${this.subdomain}/manage/e/${encodeURIComponent(this.tokenPayload.token)}/cancel`
                    : `/book/${this.subdomain}/manage/r/${encodeURIComponent(this.tokenPayload.token)}/cancel`;
                const response = await fetch(path, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                    },
                });
                const data = await response.json();
                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || 'Cancel failed');
                }
                this.record = data.record;
                this.successMessage = @js(__('Booking cancelled.'));
            } catch (e) {
                this.error = e.message || 'Cancel failed';
            } finally {
                this.loading = false;
            }
        },

        async startReschedule() {
            this.view = 'reschedule';
            this.error = '';
            this.successMessage = '';
            this.selectedDate = '';
            this.selectedSlotId = '';
            this.slots = [];
            await this.loadDates();
        },

        async loadDates() {
            if (!this.record?.source) return;
            this.loadingDates = true;
            try {
                const params = new URLSearchParams({
                    booking_key: this.bookingKey,
                    source: this.record.source,
                });
                if (this.record.duration_minutes) {
                    params.set('duration_minutes', this.record.duration_minutes);
                }
                const response = await fetch('/api/reminders/availability?' + params.toString());
                const data = await response.json();
                this.dates = data.dates || [];
            } catch (e) {
                this.error = 'Could not load dates';
            } finally {
                this.loadingDates = false;
            }
        },

        async loadSlots() {
            this.selectedSlotId = '';
            this.slots = [];
            if (!this.selectedDate || !this.record?.source) return;
            this.loadingSlots = true;
            try {
                const params = new URLSearchParams({
                    booking_key: this.bookingKey,
                    source: this.record.source,
                    date: this.selectedDate,
                });
                if (this.record.duration_minutes) {
                    params.set('duration_minutes', this.record.duration_minutes);
                }
                const response = await fetch('/api/reminders/availability?' + params.toString());
                const data = await response.json();
                this.slots = data.available_slots || [];
            } catch (e) {
                this.error = 'Could not load times';
            } finally {
                this.loadingSlots = false;
            }
        },

        async confirmReschedule() {
            this.error = '';
            this.loading = true;
            try {
                const response = await fetch(`/book/${this.subdomain}/manage/r/${encodeURIComponent(this.tokenPayload.token)}/reschedule`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                    },
                    body: JSON.stringify({ slot_id: this.selectedSlotId }),
                });
                const data = await response.json();
                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || 'Reschedule failed');
                }
                this.record = data.record;
                this.view = 'detail';
                this.successMessage = @js(__('Booking rescheduled.'));
            } catch (e) {
                this.error = e.message || 'Reschedule failed';
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
</body>
</html>
