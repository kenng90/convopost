<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $company->name }} — Book appointment</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div
    class="max-w-lg mx-auto p-6"
    x-data="bookingCatalog({
        token: @js($token),
        services: @js($services),
        subdomain: @js($company->subdomain),
    })"
>
    <div class="bg-white rounded-2xl shadow-lg p-6 space-y-6">
        <div>
            <p class="text-sm text-slate-500">{{ $company->name }}</p>
            <h1 class="text-2xl font-semibold text-slate-900">Book an appointment</h1>
            <p class="text-sm text-slate-500 mt-1">Choose a service to continue.</p>
        </div>

        <div class="space-y-2">
            <template x-for="service in services" :key="service.id">
                <a
                    :href="`/book/${subdomain}/${encodeURIComponent(service.name)}?token=${encodeURIComponent(token)}`"
                    class="block rounded-xl border border-slate-200 px-4 py-3 hover:border-violet-400 hover:bg-violet-50 transition"
                >
                    <p class="font-medium text-slate-900" x-text="service.name"></p>
                    <p class="text-xs text-slate-500" x-text="`${service.default_duration_minutes} min default`"></p>
                </a>
            </template>
        </div>

        <p x-show="!services.length" class="text-sm text-slate-500">No bookable services are available right now.</p>
    </div>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function bookingCatalog(config) {
    return {
        token: config.token,
        services: config.services || [],
        subdomain: config.subdomain,
    };
}
</script>
</body>
</html>
