<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $source->name }} — Book appointment</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div
    class="max-w-lg mx-auto p-6"
    x-data="bookingWidget({
        token: @js($token),
        source: @js($source->name),
        services: @js($services ?? []),
        subdomain: @js($company->subdomain),
        durationOptions: @js($source->durationOptions()),
        timezone: @js($source->timezone),
        showServicePicker: @js($showServicePicker ?? count($services ?? []) > 1),
    })"
>
    <div class="bg-white rounded-2xl shadow-lg p-6 space-y-6">
        <div>
            <p class="text-sm text-slate-500">{{ $company->name }}</p>
            <h1 class="text-2xl font-semibold text-slate-900" x-text="source || 'Book appointment'"></h1>
        </div>

        <div x-show="showServicePicker">
            <label class="block text-sm font-medium text-slate-700 mb-2">Service</label>
            <select x-model="source" @change="onServiceChange()" class="w-full rounded-lg border-slate-300">
                <template x-for="service in services" :key="service.id">
                    <option :value="service.name" x-text="service.name"></option>
                </template>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Duration</label>
            <select x-model.number="durationMinutes" @change="loadDates()" class="w-full rounded-lg border-slate-300">
                <template x-for="option in durationOptions" :key="option">
                    <option :value="option" x-text="option + ' minutes'"></option>
                </template>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Date</label>
            <select x-model="selectedDate" @change="loadSlots()" class="w-full rounded-lg border-slate-300">
                <option value="">Select a date</option>
                <template x-for="date in dates" :key="date">
                    <option :value="date" x-text="date"></option>
                </template>
            </select>
        </div>

        <div x-show="slots.length">
            <label class="block text-sm font-medium text-slate-700 mb-2">Available times</label>
            <div class="grid grid-cols-1 gap-2">
                <template x-for="slot in slots" :key="slot.id">
                    <button
                        type="button"
                        @click="selectedSlot = slot.id"
                        class="rounded-lg border px-4 py-3 text-left transition"
                        :class="selectedSlot === slot.id ? 'border-violet-600 bg-violet-50' : 'border-slate-200 hover:border-violet-300'"
                    >
                        <span x-text="slot.title"></span>
                    </button>
                </template>
            </div>
        </div>

        <div class="space-y-3">
            <input type="text" x-model="name" placeholder="Your name" class="w-full rounded-lg border-slate-300">
            <input type="tel" x-model="phone" placeholder="Phone number" class="w-full rounded-lg border-slate-300">
        </div>

        <button
            type="button"
            @click="book()"
            :disabled="loading || !selectedSlot || !name || !phone || !source"
            class="w-full rounded-lg bg-violet-600 text-white py-3 font-medium disabled:opacity-50"
        >
            <span x-text="loading ? 'Booking…' : 'Confirm booking'"></span>
        </button>

        <p x-show="message" x-text="message" class="text-sm" :class="success ? 'text-green-600' : 'text-red-600'"></p>
    </div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function bookingWidget(config) {
    return {
        token: config.token,
        source: config.source,
        services: config.services || [],
        subdomain: config.subdomain,
        durationOptions: config.durationOptions,
        durationMinutes: config.durationOptions[0] || 30,
        showServicePicker: config.showServicePicker,
        dates: [],
        slots: [],
        selectedDate: '',
        selectedSlot: '',
        name: '',
        phone: '',
        loading: false,
        message: '',
        success: false,

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

        async loadDates() {
            this.selectedDate = '';
            this.selectedSlot = '';
            this.slots = [];
            this.message = '';

            const params = new URLSearchParams({
                token: this.token,
                source: this.source,
                duration_minutes: this.durationMinutes,
            });

            const response = await fetch('/api/reminders/availability?' + params.toString());
            const data = await response.json();
            this.dates = data.dates || [];
            if (data.duration_options?.length) {
                this.durationOptions = data.duration_options;
            }
        },

        async loadSlots() {
            this.selectedSlot = '';
            this.slots = [];
            this.message = '';

            if (!this.selectedDate) {
                return;
            }

            const params = new URLSearchParams({
                token: this.token,
                source: this.source,
                date: this.selectedDate,
                duration_minutes: this.durationMinutes,
            });

            const response = await fetch('/api/reminders/availability?' + params.toString());
            const data = await response.json();
            this.slots = data.slots || [];
        },

        async book() {
            this.loading = true;
            this.message = '';

            try {
                const response = await fetch('/api/reminders/reservation/makeReservation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        token: this.token,
                        source: this.source,
                        slot_id: this.selectedSlot,
                        name: this.name,
                        phone: this.phone,
                    }),
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Booking failed');
                }

                this.success = true;
                this.message = 'Booking confirmed!';
            } catch (error) {
                this.success = false;
                this.message = error.message || 'Booking failed';
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
</body>
</html>
