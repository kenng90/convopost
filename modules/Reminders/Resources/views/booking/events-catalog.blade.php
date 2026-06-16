<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $company->name }} — {{ __('Events') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-lg mx-auto p-6 space-y-4">
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <p class="text-sm text-slate-500">{{ $company->name }}</p>
        <h1 class="text-2xl font-semibold text-slate-900">{{ __('Upcoming events') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Register for a session below.') }}</p>
        <div class="mt-4 flex gap-2 text-sm">
            <a href="{{ route('reminders.booking.catalog', ['subdomain' => $company->subdomain, 'token' => $token]) }}" class="text-violet-600 hover:underline">{{ __('Book an appointment') }}</a>
        </div>
    </div>

    @forelse ($events as $event)
        <div class="bg-white rounded-2xl shadow-lg p-6 space-y-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">{{ $event['title'] }}</h2>
                @if ($event['description'])
                    <p class="text-sm text-slate-600 mt-1">{{ $event['description'] }}</p>
                @endif
                @if ($event['location'])
                    <p class="text-xs text-slate-500 mt-2">{{ $event['location'] }}</p>
                @endif
            </div>
            <div class="space-y-2">
                @foreach ($event['occurrences'] as $occurrence)
                    @if ($occurrence['is_registerable'])
                        <a
                            href="{{ route('reminders.booking.event', ['subdomain' => $company->subdomain, 'occurrence' => $occurrence['id'], 'token' => $token]) }}"
                            class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 hover:border-violet-400 hover:bg-violet-50 transition"
                        >
                            <span class="text-sm font-medium text-slate-900">{{ $occurrence['starts_at_label'] }}</span>
                            <span class="text-xs text-slate-500">{{ $occurrence['seats_remaining'] }} {{ __('seats left') }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white rounded-2xl shadow-lg p-6 text-sm text-slate-500">
            {{ __('No upcoming events are open for registration.') }}
        </div>
    @endforelse
</div>
</body>
</html>
