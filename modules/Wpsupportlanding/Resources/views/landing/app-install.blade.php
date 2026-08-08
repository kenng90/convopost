<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Install agent app — {{ $appName }}</title>
    <meta name="description" content="Private install page for the {{ $appName }} agent mobile app.">
    @include('wpsupportlanding::landing.partials.marketing_styles')
</head>
<body class="bg-[#040f0c] text-white min-h-screen">
    @include('wpsupportlanding::landing.partials.marketing_nav', ['hasBlog' => $hasBlog ?? false])

    <section class="relative pt-28 pb-20 noise hero-glow min-h-screen">
        <div class="relative z-10 max-w-2xl mx-auto px-4 sm:px-6">
            <div class="mb-10">
                <div class="badge inline-flex mb-4">Agent app</div>
                <h1 class="font-display text-4xl sm:text-5xl font-800 mb-3">Install {{ $appName }}</h1>
                <p class="text-base text-gray-400 leading-relaxed">
                    Private build for your support team. Sign in with your {{ $appName }} agent account after installing.
                </p>
                <p class="mt-3 text-sm text-gray-500">Version {{ $appVersion }}</p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6 sm:p-8 space-y-6">
                @if ($androidUrl)
                    <div>
                        <h2 class="font-display text-xl font-700 mb-2">Android</h2>
                        <p class="text-sm text-gray-400 mb-4">
                            Download the signed APK, then allow install from this browser if Android asks.
                        </p>
                        <a
                            href="{{ $androidUrl }}"
                            class="inline-flex items-center justify-center w-full sm:w-auto px-6 py-3 rounded-xl text-black font-semibold transition hover:opacity-90"
                            style="background:#25D366;"
                            rel="noopener"
                        >
                            Download Android app
                        </a>
                    </div>
                @else
                    <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                        Android download is not configured yet. Ask your admin to set
                        <code class="text-xs">MOBILE_APP_ANDROID_URL</code> after running an EAS preview build.
                    </div>
                @endif

                @if ($iosUrl)
                    <div class="pt-2 border-t border-white/10">
                        <h2 class="font-display text-xl font-700 mb-2">iPhone</h2>
                        <p class="text-sm text-gray-400 mb-4">Install via TestFlight or the App Store link provided by your admin.</p>
                        <a
                            href="{{ $iosUrl }}"
                            class="inline-flex items-center justify-center w-full sm:w-auto px-6 py-3 rounded-xl border border-white/20 text-white font-semibold hover:bg-white/5 transition"
                            rel="noopener"
                        >
                            Open iOS install link
                        </a>
                    </div>
                @endif
            </div>

            <div class="mt-10 space-y-4">
                <h2 class="font-display text-lg font-700">Install steps</h2>
                <ol class="space-y-3 text-sm text-gray-400 list-decimal list-inside leading-relaxed">
                    <li>Open this page on your phone (use the invite link from your admin).</li>
                    <li>Tap <span class="text-white">Download Android app</span> and wait for the file.</li>
                    <li>Open the downloaded APK and tap Install. Allow “unknown apps” for your browser if prompted.</li>
                    <li>Launch <span class="text-white">{{ $appName }}</span> and sign in with your agent email and password.</li>
                </ol>
            </div>

            <div class="mt-10 rounded-2xl border border-white/10 bg-white/[0.02] p-5 text-sm text-gray-400 space-y-2">
                <p class="text-white font-medium">Security notes</p>
                <ul class="list-disc list-inside space-y-1.5">
                    <li>Only install from this official {{ $appName }} link.</li>
                    <li>
                        Do not forward the APK in WhatsApp groups — share the invite link instead
                        @if ($tokenRequired)
                            (it includes your access token)
                        @endif.
                    </li>
                    <li>Your login still controls access to chats; uninstall access when an agent leaves the team.</li>
                </ul>
            </div>

            <div class="mt-12 pt-8 border-t border-white/10 flex flex-wrap gap-4">
                <a href="{{ url('/') }}" class="text-sm text-gray-400 hover:text-white transition-colors">← Back to home</a>
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="text-sm text-gray-400 hover:text-white transition-colors">Sign in to dashboard</a>
                @endif
            </div>
        </div>
    </section>

    <footer class="border-t border-white/[0.05] py-8" style="background:#020907;">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 text-center">
            <p class="text-xs text-gray-600">© {{ date('Y') }} {{ $appName }}. Private agent distribution.</p>
        </div>
    </footer>
</body>
</html>
