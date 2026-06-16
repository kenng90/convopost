<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $company->name }} — {{ $occurrence['starts_at_label'] ?? __('Event') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div
    class="max-w-lg mx-auto p-6"
    x-data="eventRegister({
        token: @js($token),
        occurrenceId: @js($occurrence['id']),
        eventTitle: @js($event['title']),
        startsAtLabel: @js($occurrence['starts_at_label']),
        seatsRemaining: @js($occurrence['seats_remaining']),
    })"
>
    <div class="bg-white rounded-2xl shadow-lg p-6 space-y-6" x-show="view === 'form'">
        <div>
            <a href="{{ route('reminders.booking.events', ['subdomain' => $company->subdomain, 'token' => $token]) }}" class="text-sm text-violet-600 hover:underline">{{ __('← All events') }}</a>
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
                <input type="tel" x-model="phone" required class="w-full rounded-xl border border-slate-200 px-4 py-2.5">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Party size') }}</label>
                <input type="number" x-model.number="partySize" min="1" :max="seatsRemaining" class="w-full rounded-xl border border-slate-200 px-4 py-2.5">
            </div>
            <p x-show="errorMessage" class="text-sm text-red-600" x-text="errorMessage"></p>
            <button type="submit" :disabled="loading" class="w-full rounded-xl bg-violet-600 text-white py-3 font-medium hover:bg-violet-700 disabled:opacity-50">
                <span x-show="!loading">{{ __('Confirm registration') }}</span>
                <span x-show="loading">{{ __('Registering...') }}</span>
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-lg p-6 space-y-4 text-center" x-show="view === 'success'" x-cloak>
        <div class="text-4xl">✓</div>
        <h2 class="text-xl font-semibold text-slate-900">{{ __('You are registered!') }}</h2>
        <p class="text-sm text-slate-600" x-text="confirmationLine"></p>
        <a href="{{ route('reminders.booking.events', ['subdomain' => $company->subdomain, 'token' => $token]) }}" class="inline-block text-sm text-violet-600 hover:underline">{{ __('Browse more events') }}</a>
    </div>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function eventRegister(config) {
    return {
        token: config.token,
        occurrenceId: config.occurrenceId,
        eventTitle: config.eventTitle,
        startsAtLabel: config.startsAtLabel,
        seatsRemaining: config.seatsRemaining,
        name: '',
        phone: '',
        partySize: 1,
        view: 'form',
        loading: false,
        errorMessage: '',
        confirmationLine: '',
        async register() {
            this.loading = true;
            this.errorMessage = '';
            try {
                const response = await fetch('/api/reminders/events/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        token: this.token,
                        occurrence_id: this.occurrenceId,
                        name: this.name.trim(),
                        phone: this.phone.trim(),
                        party_size: this.partySize,
                    }),
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || '{{ __('Registration failed.') }}');
                }
                this.confirmationLine = `${this.eventTitle} — ${this.startsAtLabel}`;
                this.view = 'success';
            } catch (error) {
                this.errorMessage = error.message;
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
</body>
</html>
