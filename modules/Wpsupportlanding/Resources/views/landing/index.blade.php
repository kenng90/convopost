<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
@php
  $siteName = config('settings.site_name', config('app.name'));
  $metaTitle = $siteName.' — Omnichannel Social Commerce for WhatsApp, Instagram & Messenger';
  $metaDescription = 'Sell, support, and get paid on WhatsApp, Instagram & Messenger. Recover carts, convert bookings, and collect unpaid invoices with M-Pesa STK retry, Paystack fallback, and WhatsApp chase — one platform.';
  $registrationEnabled = !config('settings.disable_registration_page', false);
@endphp
@include('layouts.favicon')
<title>{{ $metaTitle }}</title>
<meta name="title" content="{{ $metaTitle }}">
<meta name="description" content="{{ $metaDescription }}" />
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:title" content="{{ $metaTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $metaTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
<link rel="canonical" href="{{ url()->current() }}">
@include('layouts.seo-schema', ['schemaSiteName' => $siteName, 'schemaDescription' => $metaDescription])
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet" />
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        wa: {
          green: '#25D366',
          dark: '#128C7E',
          darker: '#075E54',
          light: '#dcfce7',
        }
      },
      fontFamily: {
        display: ['Syne', 'sans-serif'],
        body: ['DM Sans', 'sans-serif'],
      }
    }
  }
}
</script>
<style>
  *, body { font-family: 'DM Sans', sans-serif; font-size: 17px; line-height: 1.65; }
  h1,h2,h3,h4,h5,.font-display { font-family: 'Syne', sans-serif; }

  :root {
    --wa-green: #25D366;
    --wa-dark: #128C7E;
    --wa-darker: #075E54;
    --hero-bg: #f4faf7;
  }

  html { scroll-padding-top: 72px; }

  /* Noise texture overlay */
  .noise::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.025'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 0;
  }

  /* Gradient mesh */
  .hero-glow {
    background: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(37,211,102,0.18) 0%, transparent 70%),
                radial-gradient(ellipse 50% 40% at 80% 60%, rgba(18,140,126,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 40% 30% at 10% 80%, rgba(7,94,84,0.10) 0%, transparent 50%);
  }

  /* Grid pattern */
  .grid-pattern {
    background-image: linear-gradient(rgba(7,94,84,0.06) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(7,94,84,0.06) 1px, transparent 1px);
    background-size: 48px 48px;
  }

  /* Float animation */
  @keyframes float { 0%,100% { transform: translateY(0px); } 50% { transform: translateY(-12px); } }
  @keyframes float2 { 0%,100% { transform: translateY(0px) rotate(-2deg); } 50% { transform: translateY(-8px) rotate(2deg); } }
  @keyframes pulse-ring { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(1.6); opacity: 0; } }
  @keyframes slide-up { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }
  @keyframes scan { 0% { transform: translateY(-100%); } 100% { transform: translateY(400%); } }

  .float { animation: float 4s ease-in-out infinite; }
  .float2 { animation: float2 5s ease-in-out infinite; }
  .float3 { animation: float 6s ease-in-out infinite 1s; }

  .pulse-ring::after {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid var(--wa-green);
    animation: pulse-ring 1.8s ease-out infinite;
  }

  .card-lift {
    transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.25s ease;
  }
  .card-lift:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 20px 60px rgba(7,94,84,0.10);
  }

  /* Navbar blur */
  .navbar-blur {
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    background: rgba(255,255,255,0.88);
    border-bottom: 1px solid rgba(7,94,84,0.08);
  }

  /* Phone mockup */
  .phone-frame {
    background: #1a1a2e;
    border-radius: 40px;
    border: 2px solid rgba(37,211,102,0.15);
    box-shadow: 0 40px 100px rgba(7,94,84,0.18), 0 0 0 1px rgba(7,94,84,0.06);
  }
  .phone-notch {
    background: #0d0d1a;
    border-radius: 0 0 20px 20px;
    margin: 0 auto;
    width: 40%;
    height: 28px;
  }

  /* WhatsApp chat bubble */
  .bubble-in {
    background: #1f2c34;
    border-radius: 0 12px 12px 12px;
  }
  .bubble-out {
    background: #005c4b;
    border-radius: 12px 12px 0 12px;
  }

  /* Code block */
  .code-block {
    background: #0d1117;
    border: 1px solid rgba(37,211,102,0.15);
    border-radius: 12px;
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 13px;
    line-height: 1.7;
  }

  .grad-text {
    color: #128C7E;
    font-size: 1.5rem; /* Adjust as needed */
  }

  /* Flow node */
  .flow-node {
    background: rgba(37,211,102,0.08);
    border: 1px solid rgba(37,211,102,0.25);
    border-radius: 12px;
    transition: all 0.2s ease;
  }
  .flow-node:hover {
    background: rgba(37,211,102,0.14);
    border-color: rgba(37,211,102,0.45);
    transform: scale(1.02);
  }
  .flow-line {
    width: 2px;
    background: linear-gradient(to bottom, rgba(37,211,102,0.4), rgba(37,211,102,0.1));
    margin: 0 auto;
    height: 24px;
  }

  /* Pricing card */
  .pricing-popular {
    background: linear-gradient(135deg, rgba(37,211,102,0.08), rgba(18,140,126,0.05));
    border: 1px solid rgba(37,211,102,0.3);
    position: relative;
  }
  .pricing-popular::before {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(37,211,102,0.3), rgba(18,140,126,0.1));
    z-index: -1;
  }

  /* Stat bar */
  .stat-item {
    position: relative;
  }
  .stat-item + .stat-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 20%;
    height: 60%;
    width: 1px;
    background: rgba(7,94,84,0.15);
  }

  /* Badge */
  .badge {
    background: rgba(37,211,102,0.1);
    border: 1px solid rgba(37,211,102,0.25);
    color: #25D366;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 999px;
  }

  /* Scrollbar */
  ::-webkit-scrollbar { width: 6px; }
  ::-webkit-scrollbar-track { background: #eef5f1; }
  ::-webkit-scrollbar-thumb { background: rgba(37,211,102,0.3); border-radius: 3px; }

  /* Accordion transition */
  [x-cloak] { display: none !important; }

  /* Dots */
  .dots-bg {
    background-image: radial-gradient(rgba(7,94,84,0.1) 1px, transparent 1px);
    background-size: 24px 24px;
  }

  /* Integration card */
  .integration-card {
    background: #ffffff;
    border: 1px solid rgba(7,94,84,0.1);
    transition: all 0.2s ease;
  }
  .integration-card:hover {
    background: rgba(37,211,102,0.06);
    border-color: rgba(37,211,102,0.35);
    transform: translateY(-3px);
  }

  @media (prefers-reduced-motion: reduce) {
    .float, .float2, .float3, .pulse-ring::after { animation: none !important; }
    .card-lift, .integration-card, .flow-node { transition: none !important; }
  }

  a:focus-visible, button:focus-visible {
    outline: 2px solid #25D366;
    outline-offset: 2px;
  }

  /* Nominal-inspired editorial surfaces */
  .section-label {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #25D366;
  }
  .hero-brand {
    font-size: clamp(2rem, 3.6vw, 3.25rem);
    line-height: 1;
    letter-spacing: -0.03em;
    text-transform: none;
  }
  .hero-title {
    font-size: clamp(2.85rem, 6vw, 4.75rem);
    line-height: 1.05;
    letter-spacing: -0.035em;
  }
  .section-title {
    font-size: clamp(2.15rem, 4.2vw, 3.35rem);
    line-height: 1.12;
    letter-spacing: -0.03em;
  }
  .body-lg {
    font-size: clamp(1.125rem, 1.4vw, 1.25rem);
    line-height: 1.7;
  }
  .hero-phone {
    width: min(100%, 320px);
    height: 560px;
    margin-left: auto;
    margin-right: auto;
    transform: rotate(-2deg);
    box-shadow: 0 32px 80px rgba(7,94,84,0.16), 0 0 0 1px rgba(7,94,84,0.06);
  }
  @media (min-width: 1024px) {
    .hero-phone {
      margin-right: 0;
      transform: rotate(-3deg) translateY(8px);
    }
  }
  .infographic-panel {
    box-shadow: 0 24px 60px rgba(7,94,84,0.08), inset 0 1px 0 rgba(255,255,255,0.8);
  }
  .outcome-num {
    font-size: clamp(3rem, 8vw, 5.5rem);
    line-height: 0.95;
    letter-spacing: -0.03em;
  }
  .reveal-line {
    background: linear-gradient(90deg, transparent, rgba(37,211,102,0.35), transparent);
    height: 1px;
  }
  @keyframes dash-flow {
    to { stroke-dashoffset: -24; }
  }
  .infographic-panel svg path[stroke-dasharray] {
    animation: dash-flow 2.4s linear infinite;
  }
  @media (prefers-reduced-motion: reduce) {
    .infographic-panel svg path[stroke-dasharray] { animation: none !important; }
  }
</style>
</head>

<body class="bg-[#f7faf8] text-gray-900" x-data="{ mobileOpen: false, productOpen: false, mobileProductOpen: false, billing: 'monthly', faqOpen: null }" @keydown.escape.window="productOpen = false; mobileOpen = false">

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:px-4 focus:py-2 focus:rounded-lg focus:text-black focus:font-semibold" style="background:#25D366;">Skip to content</a>

<!-- ===== NAVBAR ===== -->
<nav class="navbar-blur fixed top-0 left-0 right-0 z-50" style="height:72px;" @click.outside="productOpen = false">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6 flex items-center justify-between h-full">
    <!-- Logo -->
    <a href="{{ route('landing') }}" class="flex items-center gap-2.5 flex-shrink-0">
      <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#25D366,#075E54);">
      <img src="{{ config('settings.logo', asset('favicon.ico')) }}" alt="{{ config('settings.site_name', config('app.name')) }}" class="w-full h-full object-contain p-1" />
      </div>
      <span class="font-display font-800 text-lg tracking-tight text-gray-900">{{ config('settings.site_name', config('app.name')) }}</span>
    </a>

    <!-- Desktop Nav: keep 5 primary items; nest the rest under Product -->
    <div class="hidden lg:flex items-center gap-6">
      <div class="relative">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 text-[15px] text-gray-600 hover:text-gray-900 transition-colors"
          @click="productOpen = !productOpen"
          :aria-expanded="productOpen.toString()"
          aria-haspopup="true"
          aria-controls="product-menu"
        >
          Product
          <svg class="w-3.5 h-3.5 transition-transform" :class="productOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div
          id="product-menu"
          x-show="productOpen"
          x-cloak
          x-transition.opacity.duration.150ms
          class="absolute left-0 top-full mt-3 w-[22rem] rounded-2xl border border-gray-200 bg-white p-3 shadow-xl"
          style="box-shadow:0 18px 50px rgba(7,94,84,0.12);"
        >
          <div class="grid grid-cols-2 gap-1">
            <a href="#features" class="rounded-xl px-3 py-2.5 hover:bg-slate-50" @click="productOpen=false">
              <p class="text-sm font-semibold text-gray-900">Features</p>
              <p class="text-xs text-gray-500 mt-0.5">Inbox, bots, commerce</p>
            </a>
            <a href="#how-it-works" class="rounded-xl px-3 py-2.5 hover:bg-slate-50" @click="productOpen=false">
              <p class="text-sm font-semibold text-gray-900">How it works</p>
              <p class="text-xs text-gray-500 mt-0.5">Connect → engage</p>
            </a>
            <a href="#journeys" class="rounded-xl px-3 py-2.5 hover:bg-slate-50" @click="productOpen=false">
              <p class="text-sm font-semibold text-gray-900">Journeys</p>
              <p class="text-xs text-gray-500 mt-0.5">Kanban CRM pipelines</p>
            </a>
            <a href="#bookings" class="rounded-xl px-3 py-2.5 hover:bg-slate-50" @click="productOpen=false">
              <p class="text-sm font-semibold text-gray-900">Bookings</p>
              <p class="text-xs text-gray-500 mt-0.5">Appointments &amp; events</p>
            </a>
            <a href="#catalog" class="rounded-xl px-3 py-2.5 hover:bg-slate-50" @click="productOpen=false">
              <p class="text-sm font-semibold text-gray-900">Catalog</p>
              <p class="text-xs text-gray-500 mt-0.5">In-chat shop</p>
            </a>
            <a href="#collections" class="rounded-xl px-3 py-2.5 hover:bg-slate-50" @click="productOpen=false">
              <p class="text-sm font-semibold text-gray-900">Collections</p>
              <p class="text-xs text-gray-500 mt-0.5">STK retry &amp; chase</p>
            </a>
            <a href="#automation" class="rounded-xl px-3 py-2.5 hover:bg-slate-50" @click="productOpen=false">
              <p class="text-sm font-semibold text-gray-900">Automation</p>
              <p class="text-xs text-gray-500 mt-0.5">38+ Flowmaker nodes</p>
            </a>
            <a href="#integrations" class="rounded-xl px-3 py-2.5 hover:bg-slate-50" @click="productOpen=false">
              <p class="text-sm font-semibold text-gray-900">Integrations</p>
              <p class="text-xs text-gray-500 mt-0.5">Meta, shops, payments</p>
            </a>
            <a href="#api" class="rounded-xl px-3 py-2.5 hover:bg-slate-50" @click="productOpen=false">
              <p class="text-sm font-semibold text-gray-900">API</p>
              <p class="text-xs text-gray-500 mt-0.5">Developer endpoints</p>
            </a>
          </div>
        </div>
      </div>
      <a href="#channels" class="text-[15px] text-gray-600 hover:text-gray-900 transition-colors">Channels</a>
      <a href="#campaigns" class="text-[15px] text-gray-600 hover:text-gray-900 transition-colors">Campaigns</a>
      <a href="#pricing" class="text-[15px] text-gray-600 hover:text-gray-900 transition-colors">Pricing</a>
      <a href="#faq" class="text-[15px] text-gray-600 hover:text-gray-900 transition-colors">FAQ</a>
      @if(isset($hasBlog) && $hasBlog)
      <a href="/blog" class="text-[15px] text-gray-600 hover:text-gray-900 transition-colors">Blog</a>
      @endif
    </div>

    <!-- CTAs -->
    <div class="hidden lg:flex items-center gap-3">
      <a href="{{ route('login') }}" class="text-base text-gray-700 hover:text-gray-900 transition-colors font-medium">Sign In</a>
      @if($registrationEnabled)
      <a href="{{ route('register') }}" class="text-base font-semibold px-5 py-2.5 rounded-lg text-black transition-all hover:opacity-90 hover:scale-105" style="background:#25D366;">Get Started Free</a>
      @endif
    </div>

    <!-- Mobile toggle -->
    <button type="button" class="lg:hidden text-gray-600 hover:text-gray-900" @click="mobileOpen = !mobileOpen; productOpen = false" :aria-expanded="mobileOpen.toString()" aria-controls="mobile-menu" aria-label="Toggle navigation menu">
      <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>

  <!-- Mobile Menu -->
  <div id="mobile-menu" x-show="mobileOpen" x-cloak class="lg:hidden border-t border-gray-200 px-4 py-4 space-y-1 max-h-[calc(100svh-72px)] overflow-y-auto" style="background:rgba(255,255,255,0.98);">
    <div class="rounded-xl border border-gray-100 overflow-hidden mb-1">
      <button type="button" class="w-full flex items-center justify-between px-3 py-2.5 text-base text-gray-800 font-medium" @click="mobileProductOpen = !mobileProductOpen" :aria-expanded="mobileProductOpen.toString()">
        Product
        <svg class="w-4 h-4 text-gray-500 transition-transform" :class="mobileProductOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </button>
      <div x-show="mobileProductOpen" x-cloak class="border-t border-gray-100 bg-slate-50 px-2 py-2 space-y-0.5">
        <a href="#features" class="block rounded-lg px-3 py-2 text-sm text-gray-700" @click="mobileOpen=false">Features</a>
        <a href="#how-it-works" class="block rounded-lg px-3 py-2 text-sm text-gray-700" @click="mobileOpen=false">How it works</a>
        <a href="#journeys" class="block rounded-lg px-3 py-2 text-sm text-gray-700" @click="mobileOpen=false">Journeys</a>
        <a href="#bookings" class="block rounded-lg px-3 py-2 text-sm text-gray-700" @click="mobileOpen=false">Bookings</a>
        <a href="#catalog" class="block rounded-lg px-3 py-2 text-sm text-gray-700" @click="mobileOpen=false">Catalog</a>
        <a href="#collections" class="block rounded-lg px-3 py-2 text-sm text-gray-700" @click="mobileOpen=false">Collections</a>
        <a href="#automation" class="block rounded-lg px-3 py-2 text-sm text-gray-700" @click="mobileOpen=false">Automation</a>
        <a href="#integrations" class="block rounded-lg px-3 py-2 text-sm text-gray-700" @click="mobileOpen=false">Integrations</a>
        <a href="#api" class="block rounded-lg px-3 py-2 text-sm text-gray-700" @click="mobileOpen=false">API</a>
      </div>
    </div>
    <a href="#channels" class="block py-2.5 text-base text-gray-700 hover:text-gray-900" @click="mobileOpen=false">Channels</a>
    <a href="#campaigns" class="block py-2.5 text-base text-gray-700 hover:text-gray-900" @click="mobileOpen=false">Campaigns</a>
    <a href="#pricing" class="block py-2.5 text-base text-gray-700 hover:text-gray-900" @click="mobileOpen=false">Pricing</a>
    <a href="#faq" class="block py-2.5 text-base text-gray-700 hover:text-gray-900" @click="mobileOpen=false">FAQ</a>
    @if(isset($hasBlog) && $hasBlog)
    <a href="/blog" class="block py-2.5 text-base text-gray-700 hover:text-gray-900" @click="mobileOpen=false">Blog</a>
    @endif
    <div class="pt-3 flex flex-col gap-2">
      <a href="{{ route('login') }}" class="text-center py-2.5 text-base text-gray-700 border border-gray-200 rounded-lg">Sign In</a>
      @if($registrationEnabled)
      <a href="{{ route('register') }}" class="text-center py-2.5 text-sm font-semibold text-black rounded-lg" style="background:#25D366;">Get Started Free</a>
      @endif
    </div>
  </div>
</nav>

<!-- ===== HERO ===== -->
<main id="main-content">
<section class="relative min-h-[100svh] flex items-center pt-28 pb-16 overflow-hidden noise">
  <div class="absolute inset-0 z-0">
    <div class="absolute inset-0" style="background:
      radial-gradient(ellipse 70% 55% at 78% 42%, rgba(37,211,102,0.16) 0%, transparent 58%),
      radial-gradient(ellipse 50% 40% at 12% 70%, rgba(18,140,126,0.10) 0%, transparent 55%),
      linear-gradient(180deg, #eef8f2 0%, #f7faf8 50%, #ffffff 100%);"></div>
    <div class="absolute inset-0 grid-pattern opacity-30"></div>
  </div>

  <div class="relative z-10 max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6 w-full">
    <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
      {{-- Copy --}}
      <div class="max-w-2xl">
        <p class="font-display hero-brand font-800 text-gray-900 mb-4">{{ $siteName }}</p>
        <h1 class="font-display hero-title font-800 text-gray-900 mb-5">
         Sell, support and grow across<br class="hidden sm:block" />
        <span class="hero-title font-800 grad-text">WhatsApp, Instagram &amp; Messenger</span>
        </h1>
        <p class="body-lg text-gray-600 mb-6">
          One shared inbox, journeys, bookings, catalogs, and payments - plus WhatsApp, SMS, and email campaigns from the same platform.
        </p>
        <div class="flex flex-wrap gap-2 mb-8" aria-label="Supported messaging channels">
          <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3.5 py-1.5 text-sm font-semibold text-gray-800">
            <span class="w-2 h-2 rounded-full" style="background:#25D366;"></span>WhatsApp
          </span>
          <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3.5 py-1.5 text-sm font-semibold text-gray-800">
            <span class="w-2 h-2 rounded-full" style="background:#E1306C;"></span>Instagram
          </span>
          <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3.5 py-1.5 text-sm font-semibold text-gray-800">
            <span class="w-2 h-2 rounded-full" style="background:#0084FF;"></span>Messenger
          </span>
        </div>
        <div class="flex flex-wrap gap-3">
          @if($registrationEnabled)
          <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-7 py-4 text-base font-semibold text-black rounded-xl transition-all hover:opacity-90" style="background:#25D366;">
            Start Free Today
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
          </a>
          @else
          <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-7 py-4 text-base font-semibold text-black rounded-xl transition-all hover:opacity-90" style="background:#25D366;">
            Sign In
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
          </a>
          @endif
          <a href="#campaigns" class="inline-flex items-center gap-2 px-7 py-4 text-base font-semibold text-gray-800 rounded-xl border border-gray-200 bg-white/70 hover:border-gray-300 transition-all hover:bg-white">
            See campaigns
          </a>
        </div>
        <p class="mt-6 text-sm text-gray-500">
          Need help beyond chat?
          <a href="{{ route('services.automation') }}" class="group inline-flex items-center gap-1.5 ml-1 font-semibold text-gray-800 transition-colors hover:text-[#128C7E]">
            <span class="border-b border-dashed border-gray-400 group-hover:border-[#128C7E] transition-colors">I want to automate processes across my business</span>
            <svg class="w-3.5 h-3.5 opacity-60 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
          </a>
        </p>
      </div>

      {{-- Product visual — real omnichannel chats inbox --}}
      <div class="relative flex justify-center lg:justify-end">
        <div class="absolute -inset-8 rounded-full blur-3xl opacity-40 pointer-events-none" style="background:radial-gradient(circle,rgba(37,211,102,0.35),transparent 70%);"></div>
        <div class="phone-frame hero-phone overflow-hidden float relative z-10" style="background:#f4f6f8;">
          <div class="phone-notch" style="background:#0f3d36;"></div>
          <div class="px-3 pt-2 pb-2.5" style="background:#128C7E;">
            <div class="flex items-center gap-2 mb-2.5">
              <svg class="w-4 h-4 text-white/90" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
              <p class="text-sm font-semibold text-white">Chats</p>
            </div>
            <div class="rounded-full bg-white px-3 py-1.5 text-[10px] text-gray-400">Search name, channel, message</div>
          </div>
          <div class="px-2.5 pt-2 pb-1 space-y-1.5" style="background:#f4f6f8;">
            <div class="flex gap-1.5 overflow-hidden">
              <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-semibold text-white" style="background:#0f3d36;">All</span>
              <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-medium text-gray-600 bg-white border border-gray-200">WhatsApp</span>
              <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-medium text-gray-600 bg-white border border-gray-200">Instagram</span>
              <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-medium text-gray-600 bg-white border border-gray-200">Messenger</span>
            </div>
            <div class="flex gap-1.5 overflow-hidden">
              <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-semibold text-white" style="background:#0f3d36;">Open</span>
              <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-medium text-gray-600 bg-white border border-gray-200">Unread</span>
              <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-medium text-gray-600 bg-white border border-gray-200">Mine</span>
              <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-medium text-gray-600 bg-white border border-gray-200">AI</span>
              <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-medium text-gray-600 bg-white border border-gray-200">Handoff</span>
            </div>
          </div>
          <div class="bg-white" style="height:calc(100% - 148px); overflow:hidden;">
            <div class="flex items-center gap-2.5 px-3 py-2.5 border-b border-gray-100">
              <div class="w-9 h-9 rounded-full bg-gray-200 flex-shrink-0"></div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <p class="text-[11px] font-semibold text-gray-900 truncate">Amina Wanjiku</p>
                  <span class="text-[9px] text-gray-400 flex-shrink-0">09:13</span>
                </div>
                <div class="flex items-center justify-between gap-2 mt-0.5">
                  <p class="text-[10px] text-gray-500">Whatsapp</p>
                  <span class="text-[8px] font-bold px-1.5 py-0.5 rounded" style="background:#cfe8ff;color:#1d4ed8;">AI</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2.5 px-3 py-2.5 border-b border-gray-100">
              <div class="w-9 h-9 rounded-full bg-gray-200 flex-shrink-0"></div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <p class="text-[11px] font-semibold text-gray-900 truncate">+254 712 ··· 884</p>
                  <span class="text-[9px] text-gray-400 flex-shrink-0">09:13</span>
                </div>
                <div class="flex items-center justify-between gap-2 mt-0.5">
                  <p class="text-[10px] text-gray-500">Whatsapp</p>
                  <span class="text-[8px] font-bold px-1.5 py-0.5 rounded" style="background:#cfe8ff;color:#1d4ed8;">AI</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2.5 px-3 py-2.5 border-b border-gray-100">
              <div class="w-9 h-9 rounded-full bg-gray-200 flex-shrink-0"></div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <p class="text-[11px] font-semibold text-gray-900 truncate flex items-center gap-1">
                    Messenger user
                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full text-[7px] font-bold text-white" style="background:#0084FF;">MS</span>
                  </p>
                  <span class="text-[9px] text-gray-400 flex-shrink-0">09:13</span>
                </div>
                <p class="text-[10px] text-gray-500 mt-0.5">Messenger</p>
              </div>
            </div>
            <div class="flex items-center gap-2.5 px-3 py-2.5">
              <div class="w-9 h-9 rounded-full bg-gray-200 flex-shrink-0"></div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <p class="text-[11px] font-semibold text-gray-900 truncate flex items-center gap-1">
                    stylehive.ke
                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full text-[7px] font-bold text-white" style="background:linear-gradient(135deg,#F58529,#E1306C);">IG</span>
                  </p>
                  <span class="text-[9px] text-gray-400 flex-shrink-0">08:51</span>
                </div>
                <p class="text-[10px] text-gray-500 mt-0.5">Instagram</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== STATS / OUTCOMES ===== -->
<section class="border-y border-gray-200 py-16" style="background:#eef8f3;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6 mb-12">
      <div>
        <p class="section-label mb-3">Outcomes</p>
        <h2 class="font-display section-title font-800 text-gray-900">Sell the result, not the inbox:<br/>recovered carts, kept bookings, paid invoices.</h2>
      </div>
      <p class="text-gray-600 max-w-md text-base leading-relaxed">Cart Recovery, Booking Convert, and Lead-to-Cash playbooks run in the same thread as your inbox, catalog, and collections engine.</p>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-4">
      <div class="stat-item px-2 lg:px-6">
        <p class="font-display outcome-num font-800 grad-text mb-2">3</p>
        <p class="text-base text-gray-600">Messaging channels: WhatsApp, Instagram, Messenger</p>
      </div>
      <div class="stat-item px-2 lg:px-6">
        <p class="font-display outcome-num font-800 grad-text mb-2">3</p>
        <p class="text-base text-gray-600">Campaign channels: WhatsApp, SMS, email</p>
      </div>
      <div class="stat-item px-2 lg:px-6">
        <p class="font-display outcome-num font-800 grad-text mb-2">~15 min</p>
        <p class="text-base text-gray-600">Guided path to first reply</p>
      </div>
      <div class="stat-item px-2 lg:px-6">
        <p class="font-display outcome-num font-800 grad-text mb-2">38+</p>
        <p class="text-base text-gray-600">Automation node types in Flowmaker</p>
      </div>
    </div>
  </div>
</section>

<!-- ===== MESSAGING CHANNELS ===== -->
<section id="channels" class="py-24 border-b border-gray-200 bg-white">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-12 max-w-3xl">
      <p class="section-label mb-3">Messaging channels</p>
      <h2 class="font-display section-title font-800 mb-4">We support <span class="grad-text">WhatsApp, Instagram &amp; Messenger</span></h2>
      <p class="text-gray-600 body-lg">One Customer 360 inbox for every Meta conversation. Reply, automate with Flowmaker, take bookings, and sell — whether customers message on WhatsApp, Instagram DM, or Facebook Messenger. Filter chats by channel just like in the app.</p>
    </div>
    <div class="grid md:grid-cols-3 gap-5 mb-10">
      <div class="rounded-3xl border border-gray-200 p-7" style="background:linear-gradient(180deg,rgba(37,211,102,0.08),transparent);">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-5 text-black font-display font-800 text-sm" style="background:#25D366;">WA</div>
        <h3 class="font-display text-2xl font-800 text-gray-900 mb-2">WhatsApp</h3>
        <p class="text-base text-gray-600 leading-relaxed mb-4">Official Meta Cloud API — catalogs, Flows, calling, utility templates, and commerce checkout in chat.</p>
        <ul class="space-y-2 text-sm text-gray-700">
          <li class="flex gap-2"><span style="color:#25D366;">✓</span> Embedded Signup onboarding</li>
          <li class="flex gap-2"><span style="color:#25D366;">✓</span> Templates, lists, media &amp; payments</li>
          <li class="flex gap-2"><span style="color:#25D366;">✓</span> Omni flow templates for bots</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-gray-200 p-7 bg-white">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-5 text-white font-display font-800 text-sm" style="background:linear-gradient(135deg,#F58529,#E1306C,#C13584);">IG</div>
        <h3 class="font-display text-2xl font-800 text-gray-900 mb-2">Instagram</h3>
        <p class="text-base text-gray-600 leading-relaxed mb-4">Instagram DMs in the same team inbox — automate replies, qualify leads, and hand off to agents.</p>
        <ul class="space-y-2 text-sm text-gray-700">
          <li class="flex gap-2"><span style="color:#E1306C;">✓</span> Shared inbox with WhatsApp chats</li>
          <li class="flex gap-2"><span style="color:#E1306C;">✓</span> Flowmaker bots &amp; quick replies</li>
          <li class="flex gap-2"><span style="color:#E1306C;">✓</span> Online booking &amp; manage links</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-gray-200 p-7 bg-white">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-5 text-white font-display font-800 text-sm" style="background:#0084FF;">MS</div>
        <h3 class="font-display text-2xl font-800 text-gray-900 mb-2">Messenger</h3>
        <p class="text-base text-gray-600 leading-relaxed mb-4">Facebook Messenger conversations alongside WhatsApp and Instagram — one CRM, one automation layer.</p>
        <ul class="space-y-2 text-sm text-gray-700">
          <li class="flex gap-2"><span style="color:#0084FF;">✓</span> Unified contact timeline</li>
          <li class="flex gap-2"><span style="color:#0084FF;">✓</span> Omni sample flows that work on Messenger</li>
          <li class="flex gap-2"><span style="color:#0084FF;">✓</span> Agent Copilot &amp; journey pipelines</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ===== CAMPAIGNS ===== -->
<section id="campaigns" class="py-24 relative overflow-hidden" style="background:#eef8f3;">
  <div class="absolute inset-0 dots-bg opacity-40"></div>
  <div class="relative z-10 max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-14">
      <div class="badge inline-flex mb-4">Outreach</div>
      <h2 class="font-display section-title font-800 mb-4">WhatsApp, SMS &amp; email<br/><span class="grad-text">campaigns</span></h2>
      <p class="body-lg text-gray-600 max-w-3xl mx-auto">Broadcast to the right audience on the right channel — same segments, scheduling, pause/resume, delivery analytics, and API-triggered sends.</p>
    </div>

    <div class="grid md:grid-cols-3 gap-5 mb-12">
      <div class="rounded-3xl border border-gray-200 bg-white p-7 card-lift">
        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] mb-3" style="color:#25D366;">WhatsApp campaigns</p>
        <h3 class="font-display text-2xl font-800 text-gray-900 mb-3">WhatsApp</h3>
        <p class="text-base text-gray-600 leading-relaxed mb-5">Approved utility and marketing templates, media, and list messages — with delivery and read analytics.</p>
        <ul class="space-y-2 text-sm text-gray-700">
          <li class="flex gap-2"><span style="color:#25D366;">→</span> Template &amp; session messaging</li>
          <li class="flex gap-2"><span style="color:#25D366;">→</span> File, group &amp; quick audiences</li>
          <li class="flex gap-2"><span style="color:#25D366;">→</span> Stage-triggered journey sends</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-gray-200 bg-white p-7 card-lift">
        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] mb-3" style="color:#0f766e;">SMS campaigns</p>
        <h3 class="font-display text-2xl font-800 text-gray-900 mb-3">SMS</h3>
        <p class="text-base text-gray-600 leading-relaxed mb-5">ConvoConnect SMS with optional Sender ID — reach customers who prefer text, at transparent rates.</p>
        <ul class="space-y-2 text-sm text-gray-700">
          <li class="flex gap-2"><span style="color:#0f766e;">→</span> ConvoConnect SMS · Sender ID</li>
          <li class="flex gap-2"><span style="color:#0f766e;">→</span> From KES 0.6 per SMS</li>
          <li class="flex gap-2"><span style="color:#0f766e;">→</span> Same campaign engine &amp; audiences</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-gray-200 bg-white p-7 card-lift">
        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] mb-3" style="color:#1d4ed8;">Email campaigns</p>
        <h3 class="font-display text-2xl font-800 text-gray-900 mb-3">Email</h3>
        <p class="text-base text-gray-600 leading-relaxed mb-5">SMTP email campaigns and notices for digests, follow-ups, and customers outside chat windows.</p>
        <ul class="space-y-2 text-sm text-gray-700">
          <li class="flex gap-2"><span style="color:#1d4ed8;">→</span> SMTP email campaigns &amp; notices</li>
          <li class="flex gap-2"><span style="color:#1d4ed8;">→</span> Shared segments &amp; scheduling</li>
          <li class="flex gap-2"><span style="color:#1d4ed8;">→</span> Delivery analytics &amp; API triggers</li>
        </ul>
      </div>
    </div>

    @include('wpsupportlanding::landing.partials.infographics.channel_orchestration')
  </div>
</section>

<!-- ===== HOW IT WORKS ===== -->
<section id="how-it-works" class="py-24 relative overflow-hidden">
  <div class="absolute inset-0 dots-bg opacity-40"></div>
  <div class="relative z-10 max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-14 max-w-2xl">
      <p class="section-label mb-3">How it works</p>
      <h2 class="font-display section-title font-800 mb-4">Connect. Orchestrate.<br/><span class="grad-text">Engage. Collect.</span></h2>
      <p class="text-gray-600">{{ $siteName }} connects WhatsApp, Instagram, and Messenger — then runs commerce, support, and campaigns so your team spends less time stitching tools and more time closing.</p>
    </div>
    @include('wpsupportlanding::landing.partials.infographics.how_it_works')
    <div class="mt-12">
      @include('wpsupportlanding::landing.partials.infographics.commerce_loop')
    </div>
  </div>
</section>

<!-- ===== WHAT WE DO ===== -->
<section class="py-24 border-b border-gray-200">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-12 max-w-2xl">
      <p class="section-label mb-3">What we do</p>
      <h2 class="font-display section-title font-800 mb-4">Social commerce that<br/><span class="grad-text">runs the work.</span></h2>
      <p class="text-gray-600">Traditional chat tools assist your team. {{ $siteName }} runs selling, support, and automation across WhatsApp, Instagram, and Messenger.</p>
    </div>
    <div class="grid lg:grid-cols-3 gap-6">
      <div class="rounded-3xl border border-gray-200 p-8" style="background:linear-gradient(180deg,rgba(37,211,102,0.06),transparent);">
        <p class="section-label mb-4">Sell</p>
        <h3 class="font-display text-3xl font-800 text-gray-900 mb-3">Commerce</h3>
        <p class="text-base text-gray-600 mb-6 leading-relaxed">Catalogs, branded shops, invoices, and collections — STK retry, Paystack fallback, and WhatsApp chase until the invoice is paid.</p>
        <ul class="space-y-2.5 text-base text-gray-700">
          <li class="flex gap-2"><span style="color:#25D366;">·</span> Product Catalog & Shop</li>
          <li class="flex gap-2"><span style="color:#25D366;">·</span> Invoices & Collections</li>
          <li class="flex gap-2"><span style="color:#25D366;">·</span> Shopify & WooCommerce sync</li>
          <li class="flex gap-2"><span style="color:#25D366;">·</span> Bookings & events</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-gray-200 p-8" style="background:#ffffff;">
        <p class="section-label mb-4">Support</p>
        <h3 class="font-display text-3xl font-800 text-gray-900 mb-3">Inbox</h3>
        <p class="text-base text-gray-600 mb-6 leading-relaxed">Shared team inbox for WhatsApp, Instagram &amp; Messenger — Customer 360, journeys, bookings, and Copilot.</p>
        <ul class="space-y-2.5 text-base text-gray-700">
          <li class="flex gap-2"><span style="color:#25D366;">·</span> Omnichannel Team Inbox</li>
          <li class="flex gap-2"><span style="color:#25D366;">·</span> Journey Pipelines</li>
          <li class="flex gap-2"><span style="color:#25D366;">·</span> WhatsApp Calling</li>
          <li class="flex gap-2"><span style="color:#25D366;">·</span> Agent Copilot</li>
        </ul>
      </div>
      <div class="rounded-3xl border border-gray-200 p-8" style="background:#ffffff;">
        <p class="section-label mb-4">Automate</p>
        <h3 class="font-display text-3xl font-800 text-gray-900 mb-3">Orchestration</h3>
        <p class="text-base text-gray-600 mb-6 leading-relaxed">WhatsApp, SMS &amp; email campaigns, native Forms, and 38+ Flowmaker nodes that work across Meta channels.</p>
        <ul class="space-y-2.5 text-base text-gray-700">
          <li class="flex gap-2"><span style="color:#25D366;">·</span> WhatsApp · SMS · Email campaigns</li>
          <li class="flex gap-2"><span style="color:#25D366;">·</span> WhatsApp Flows</li>
          <li class="flex gap-2"><span style="color:#25D366;">·</span> Omni Flow Automation</li>
          <li class="flex gap-2"><span style="color:#25D366;">·</span> Developer APIs</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ===== FEATURES GRID ===== -->
<section id="features" class="py-24 relative">
  <div class="absolute inset-0 dots-bg opacity-50"></div>
  <div class="relative z-10 max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Commerce + operations</div>
      <h2 class="font-display section-title font-800 mb-4">Everything to sell and<br/>support across Meta channels</h2>
      <p class="body-lg text-gray-600 max-w-3xl mx-auto">WhatsApp, Instagram, and Messenger inbox — plus catalogs, journeys, WhatsApp/SMS/email campaigns, bookings, Forms, and payments in one platform.</p>
    </div>

    <div class="mb-16">
      @include('wpsupportlanding::landing.partials.infographics.platform_ecosystem')
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- Card 1 -->
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.1);">👥</div>
        <h3 class="font-display font-700 text-xl mb-2 text-gray-900">Shared Team Inbox</h3>
        <p class="text-base text-gray-600 mb-4 leading-relaxed">Multi-agent inbox for WhatsApp, Instagram, and Messenger with Customer 360 — journeys, bookings, orders, and conversation context in one panel. Agent Copilot surfaces knowledge-base replies agents can accept or edit.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>WhatsApp · Instagram · Messenger</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Customer 360 unified sidebar</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Knowledge-based Copilot suggestions</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Chat assignment &amp; agent handover</li>
        </ul>
      </div>
      <!-- Card 2 -->
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.1);">📢</div>
        <h3 class="font-display font-700 text-xl mb-2 text-gray-900">WhatsApp, SMS &amp; email campaigns</h3>
        <p class="text-base text-gray-600 mb-4 leading-relaxed">One campaign engine for WhatsApp templates, ConvoConnect SMS, and SMTP email — file upload, contact groups, or quick lists — with delivery analytics and timezone-aware scheduling.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>WhatsApp, SMS &amp; email channels</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>ConvoConnect SMS · Sender ID · KES 0.6 / SMS</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>File, group &amp; quick audiences</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Segments, scheduling &amp; analytics</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>API-triggered campaign sends</li>
        </ul>
      </div>
      <!-- Card 3 -->
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.1);">🗂️</div>
        <h3 class="font-display font-700 text-xl mb-2 text-gray-900">Journey Pipelines</h3>
        <p class="text-base text-gray-600 mb-4 leading-relaxed">Kanban CRM with ready-made playbooks for sales, support, e-commerce, and events — move contacts across stages and fire WhatsApp campaigns automatically.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>7 templates: sales, support, revenue & more</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Stage-triggered WhatsApp campaigns</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Auto-enroll new contacts & group rules</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Journey sidebar in chat</li>
        </ul>
      </div>
      <!-- Card 4 -->
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.1);">⚡</div>
        <h3 class="font-display font-700 text-xl mb-2 text-gray-900">Flow Automation</h3>
        <p class="text-base text-gray-600 mb-4 leading-relaxed">Install curated flow templates in one click, or describe what you want and let the AI Flow Assistant draft automation for you to review.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Flow template library (payments, booking, support)</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>AI Flow Assistant — natural language to draft</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Visual no-code flow builder</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>38+ nodes: forms, bookings, payments &amp; AI</li>
        </ul>
      </div>
      <!-- Card 5 -->
      <div class="card-lift border rounded-2xl p-6 relative overflow-hidden" style="background:rgba(37,211,102,0.04);border-color:rgba(37,211,102,0.2);">
        <div class="absolute top-3 right-3 badge text-xs">New</div>
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.15);">📋</div>
        <h3 class="font-display font-700 text-xl mb-2 text-gray-900">WhatsApp Flows</h3>
        <p class="text-base text-gray-600 mb-4 leading-relaxed">Native interactive forms that open inside WhatsApp — then convert submissions into Flowmaker automations with booking and commerce prefills.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Drag-and-drop form builder</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>One-click publish to Meta</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Convert responses into automations</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Booking &amp; commerce data exchange</li>
        </ul>
      </div>
      <!-- Card 6 -->
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.1);">📞</div>
        <h3 class="font-display font-700 text-xl mb-2 text-gray-900">WhatsApp Calling</h3>
        <p class="text-base text-gray-600 mb-4 leading-relaxed">Inbound and outbound WhatsApp voice calls with AI-assisted greetings, post-call booking handoff, and call analytics.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Inbound &amp; outbound WhatsApp calls</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>AI voice context &amp; booking handoff</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Call analytics dashboard</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Agent performance reports</li>
        </ul>
      </div>
      <!-- Card 7 -->
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.1);">💰</div>
        <h3 class="font-display font-700 text-xl mb-2 text-gray-900">Invoices & Collections</h3>
        <p class="text-base text-gray-600 mb-4 leading-relaxed">Share public invoice pages, collect on your M-Pesa or Paystack keys, then retry, fall back, and chase until the invoice is paid — without warehousing funds.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Public invoice &amp; payment links</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>M-Pesa STK retry when PIN times out</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Paystack fallback + WhatsApp chase</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Collections board for open invoices</li>
        </ul>
      </div>
      <!-- Card 8 -->
      <div class="card-lift border rounded-2xl p-6 relative overflow-hidden" style="background:rgba(37,211,102,0.04);border-color:rgba(37,211,102,0.2);">
        <div class="absolute top-3 right-3 badge text-xs">Updated</div>
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.15);">📅</div>
        <h3 class="font-display font-700 text-xl mb-2 text-gray-900">Bookings</h3>
        <p class="text-base text-gray-600 mb-4 leading-relaxed">Full booking app with multi-staff scheduling, public widgets, events with seat limits, and automated WhatsApp reminders — configurable from Company Apps.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Multi-staff & department scheduling</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Embeddable booking widgets & events</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Google Calendar sync</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>WhatsApp reminder campaigns</li>
        </ul>
      </div>
      <!-- Card 9 -->
      <div class="card-lift border rounded-2xl p-6 relative overflow-hidden" style="background:rgba(37,211,102,0.04);border-color:rgba(37,211,102,0.2);">
        <div class="absolute top-3 right-3 badge text-xs">Updated</div>
        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-xl" style="background:rgba(37,211,102,0.15);">🛍️</div>
        <h3 class="font-display font-700 text-xl mb-2 text-gray-900">Product Catalog & Shop</h3>
        <p class="text-base text-gray-600 mb-4 leading-relaxed">Build branded WhatsApp shops, sync from Shopify or WooCommerce, and sell directly from the chat sidebar.</p>
        <ul class="space-y-2">
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Branded shop links & in-chat selling</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Store sync & inventory tracking</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Checkout via WhatsApp or invoice</li>
          <li class="flex items-start gap-2 text-base text-gray-600"><span style="color:#25D366;" class="mt-0.5 flex-shrink-0">✓</span>Catalog analytics & A/B tests</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ===== PLATFORM INTELLIGENCE ===== -->
<section id="platform" class="py-24 border-y border-gray-200 relative overflow-hidden" style="background:#f3faf6;">
  <div class="absolute inset-0 dots-bg opacity-40"></div>
  <div class="relative z-10 max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Go live faster · Run smarter</div>
      <h2 class="font-display section-title font-800 mb-4">Operations intelligence<br/>built into the platform</h2>
      <p class="body-lg text-gray-600 max-w-3xl mx-auto">From guided activation to revenue dashboards and proactive health alerts — ConvoConnect helps you launch quickly and stay on top of what matters.</p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-4" style="background:rgba(37,211,102,0.1);">🚀</div>
        <h3 class="font-display font-700 text-lg mb-2 text-gray-900">Activation OS</h3>
        <p class="text-base text-gray-600 leading-relaxed">Guided onboarding tracks WhatsApp setup, a test message, your first contacts, and first flow — so owners go live in minutes, not days.</p>
      </div>
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-4" style="background:rgba(37,211,102,0.1);">👤</div>
        <h3 class="font-display font-700 text-lg mb-2 text-gray-900">Customer 360</h3>
        <p class="text-base text-gray-600 leading-relaxed">See journeys, bookings, orders, invoices, and conversation history in one inbox sidebar — agents never hunt across tabs again.</p>
      </div>
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-4" style="background:rgba(37,211,102,0.1);">✨</div>
        <h3 class="font-display font-700 text-lg mb-2 text-gray-900">Agent Copilot</h3>
        <p class="text-base text-gray-600 leading-relaxed">Surfaces knowledge articles, quick replies, and greeting suggestions agents can accept or edit — with generative AI available in the Flow Assistant for building automations.</p>
      </div>
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-4" style="background:rgba(37,211,102,0.1);">📊</div>
        <h3 class="font-display font-700 text-lg mb-2 text-gray-900">Revenue &amp; credits</h3>
        <p class="text-base text-gray-600 leading-relaxed">Pipeline value, paid invoices, messaging credits, and managed AI credit wallets on your home screen — see usage and WhatsApp revenue at a glance.</p>
      </div>
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-4" style="background:rgba(37,211,102,0.1);">🔔</div>
        <h3 class="font-display font-700 text-lg mb-2 text-gray-900">Health Monitor</h3>
        <p class="text-base text-gray-600 leading-relaxed">Proactive alerts for disconnected numbers, failed webhooks, low credits, and stalled journeys — fix issues before customers notice.</p>
      </div>
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl mb-4" style="background:rgba(37,211,102,0.1);">👥</div>
        <h3 class="font-display font-700 text-lg mb-2 text-gray-900">Organization Managers</h3>
        <p class="text-base text-gray-600 leading-relaxed">Invite managers with scoped module access on seat-based plans — they run inbox and campaigns without full owner permissions.</p>
      </div>
    </div>
  </div>
</section>

<!-- ===== WHATSAPP FLOWS SPOTLIGHT ===== -->
<section class="py-24 border-y border-gray-200" style="background:#f3faf6;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6 grid lg:grid-cols-2 gap-16 items-center">
    <div>
      <div class="badge inline-flex mb-6">WhatsApp Native Forms</div>
      <h2 class="font-display section-title font-800 leading-tight mb-6">Forms that open<br/><span class="grad-text">inside WhatsApp.</span></h2>
      <p class="text-gray-600 leading-relaxed mb-8">WhatsApp Flows are interactive, multi-screen forms that open natively inside WhatsApp — no links, no browsers, no friction. ConvoConnect gives you a drag-and-drop builder, one-click Meta publish, and a bridge into Flowmaker for bookings and commerce.</p>
      <ul class="space-y-4 mb-8">
        <li class="flex items-start gap-3">
          <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background:rgba(37,211,102,0.15);">
            <svg class="w-3.5 h-3.5" style="color:#25D366;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          </div>
          <div>
            <p class="font-semibold text-gray-900 text-sm">All input types supported</p>
            <p class="text-sm text-gray-600">Text fields, radio buttons, dropdowns, date pickers, checkboxes, and opt-in confirmations.</p>
          </div>
        </li>
        <li class="flex items-start gap-3">
          <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background:rgba(37,211,102,0.15);">
            <svg class="w-3.5 h-3.5" style="color:#25D366;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          </div>
          <div>
            <p class="font-semibold text-gray-900 text-sm">One-click publish to Meta</p>
            <p class="text-sm text-gray-600">Build in our editor and deploy directly to Meta's servers — no JSON editing or API calls needed.</p>
          </div>
        </li>
        <li class="flex items-start gap-3">
          <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background:rgba(37,211,102,0.15);">
            <svg class="w-3.5 h-3.5" style="color:#25D366;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          </div>
          <div>
            <p class="font-semibold text-gray-900 text-sm">Forms ↔ Flow Builder bridge</p>
            <p class="text-sm text-gray-600">Convert submissions into automations with dynamic data exchange — prefill bookings, catalogs, and payment steps from form answers.</p>
          </div>
        </li>
        <li class="flex items-start gap-3">
          <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background:rgba(37,211,102,0.15);">
            <svg class="w-3.5 h-3.5" style="color:#25D366;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          </div>
          <div>
            <p class="font-semibold text-gray-900 text-sm">Response dashboard & exports</p>
            <p class="text-sm text-gray-600">View every submission in a structured table. Export to CSV or push to your CRM automatically.</p>
          </div>
        </li>
      </ul>
      @if($registrationEnabled)
      <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-5 py-3 text-sm font-semibold text-black rounded-xl" style="background:#25D366;">Build Your First Flow →</a>
      @endif
    </div>

    <!-- Form Builder Mockup -->
    <div class="relative">
      <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-2xl">
        <!-- Toolbar -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-white/[0.06]" style="background:#f8fbf9;">
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-red-500 opacity-70"></div>
            <div class="w-3 h-3 rounded-full bg-yellow-400 opacity-70"></div>
            <div class="w-3 h-3 rounded-full opacity-70" style="background:#25D366;"></div>
          </div>
          <p class="text-xs text-gray-500 font-medium">Flow Builder — Lead Capture Form</p>
          <div class="badge text-xs">Published ✓</div>
        </div>
        <div class="flex" style="min-height:320px;">
          <!-- Sidebar -->
          <div class="w-36 border-r border-white/[0.06] px-3 py-3 space-y-1.5" style="background:#f8fbf9;">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-2" style="font-size:9px;">Components</p>
            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs text-gray-400 hover:bg-white cursor-pointer transition-colors">
              <span>📝</span> Text Input
            </div>
            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs text-gray-400 hover:bg-white cursor-pointer transition-colors">
              <span>🔘</span> Radio Group
            </div>
            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs text-gray-400 hover:bg-white cursor-pointer transition-colors">
              <span>▾</span> Dropdown
            </div>
            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs text-gray-400 hover:bg-white cursor-pointer transition-colors">
              <span>📅</span> Date Picker
            </div>
            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs text-gray-400 hover:bg-white cursor-pointer transition-colors">
              <span>☑️</span> Opt-in
            </div>
            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-colors" style="color:#25D366;background:rgba(37,211,102,0.08);">
              <span>📸</span> Image
            </div>
          </div>
          <!-- Canvas -->
          <div class="flex-1 px-4 py-4 space-y-2.5">
            <p class="text-xs text-gray-500 mb-3" style="font-size:9px; text-transform:uppercase; letter-spacing:.08em;">Screen 1 of 2 · Preview</p>
            <!-- Form fields -->
            <div class="bg-[#f3faf6] border border-gray-200 rounded-xl p-3 relative group cursor-pointer" style="border-left:3px solid #25D366;">
              <div class="flex items-center justify-between mb-1">
                <p class="text-xs text-gray-400 font-medium">Full Name</p>
                <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                  <span class="text-gray-600 cursor-pointer hover:text-gray-900">✎</span>
                  <span class="text-gray-600 cursor-pointer hover:text-red-400">✕</span>
                </div>
              </div>
              <div class="h-6 bg-[#e8f5ee] rounded-md border border-white/5"></div>
              <p class="text-xs text-gray-500 mt-1" style="font-size:9px;">Required · Text</p>
            </div>
            <div class="bg-[#f8fbf9] border border-gray-200 rounded-xl p-3 group cursor-pointer hover:border-gray-200 transition-colors">
              <p class="text-xs text-gray-400 font-medium mb-1">Business Size</p>
              <div class="space-y-1">
                <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full border border-gray-600"></div><p class="text-xs text-gray-500">1–10 employees</p></div>
                <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full border flex items-center justify-center" style="border-color:#25D366;"><div class="w-1.5 h-1.5 rounded-full" style="background:#25D366;"></div></div><p class="text-xs text-gray-300">11–50 employees</p></div>
                <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full border border-gray-600"></div><p class="text-xs text-gray-500">50+ employees</p></div>
              </div>
            </div>
            <div class="bg-[#f8fbf9] border border-gray-200 rounded-xl p-3 group cursor-pointer hover:border-gray-200 transition-colors">
              <p class="text-xs text-gray-400 font-medium mb-1">Demo Date</p>
              <div class="h-6 bg-[#e8f5ee] rounded-md border border-white/5 flex items-center px-2">
                <p class="text-xs text-gray-500">MM / DD / YYYY</p>
              </div>
            </div>
            <!-- Submit -->
            <button class="w-full py-2 rounded-xl text-xs font-semibold text-black" style="background:#25D366;">Submit Form →</button>
          </div>
        </div>
        <!-- Footer -->
        <div class="flex items-center justify-between px-4 py-2.5 border-t border-gray-200" style="background:#f8fbf9;">
          <p class="text-xs text-gray-500">Drag to reorder components</p>
          <div class="flex gap-2">
            <button class="text-xs px-3 py-1 rounded-lg border border-gray-200 text-gray-400">Preview</button>
            <button class="text-xs px-3 py-1 rounded-lg text-black font-semibold" style="background:#25D366;">Publish to Meta</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== JOURNEYS SPOTLIGHT ===== -->
<section id="journeys" class="py-24 border-y border-gray-200" style="background:#f5f3ff;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Journey Pipelines</div>
      <h2 class="font-display section-title font-800 mb-4">Move every contact<br/><span class="grad-text">through a pipeline.</span></h2>
      <p class="body-lg text-gray-600 max-w-3xl mx-auto">Kanban journeys with stage automation — launch from templates, trigger WhatsApp campaigns when contacts move, and manage pipelines from the inbox sidebar.</p>
    </div>

    <div class="mb-16">
      @include('wpsupportlanding::landing.partials.infographics.journey_kanban')
    </div>

    <div class="grid md:grid-cols-3 gap-4 mb-16">
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.1);">📋</div>
        <p class="font-display font-700 text-lg mb-2 text-gray-900">1. Pick a Playbook</p>
        <p class="text-base text-gray-600 leading-relaxed">Start from sales, support, marketing, onboarding, revenue, e-commerce, events — plus Cart Recovery, Booking Convert, and Lead-to-Cash outcome playbooks.</p>
      </div>
      <div class="card-lift border rounded-2xl p-6 text-center" style="background:rgba(37,211,102,0.04);border-color:rgba(37,211,102,0.2);">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.15);">🧭</div>
        <p class="font-display font-700 text-base mb-2" style="color:#25D366;">2. Drag Contacts on Kanban</p>
        <p class="text-base text-gray-600 leading-relaxed">Move contacts across stages visually. Attach campaigns to stages so WhatsApp messages fire automatically when someone enters a step.</p>
      </div>
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.1);">📊</div>
        <p class="font-display font-700 text-lg mb-2 text-gray-900">3. Track Pipeline Value</p>
        <p class="text-base text-gray-600 leading-relaxed">See stage counts, activity history, and revenue metrics on your dashboard. Auto-enroll new contacts or route by group rules from Company Apps.</p>
      </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-12 items-center">
      <div>
        <h3 class="font-display text-2xl font-800 mb-6">Everything you need to run journeys on WhatsApp</h3>
        <div class="space-y-4">
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">🎯</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Stage-triggered campaigns</p>
              <p class="text-sm text-gray-600">Link a broadcast template to any stage. When a contact moves there — manually or via automation — the campaign queues with optional delay.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">🆕</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Auto-enroll new contacts</p>
              <p class="text-sm text-gray-600">Turn on auto-enrollment in Company Apps to add every new contact to your default journey's first stage automatically.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">💬</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Contact journeys sidebar</p>
              <p class="text-sm text-gray-600">Agents move contacts between stages, view pipeline context, and launch stage campaigns without leaving the chat inbox.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">⚙️</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Per-app settings in Company Apps</p>
              <p class="text-sm text-gray-600">Enable journeys, confirm before send, staff permissions, and default journey ID — all grouped under one Journeys tab in workspace settings.</p>
            </div>
          </div>
        </div>
        @if($registrationEnabled)
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 mt-8 px-5 py-3 text-sm font-semibold text-black rounded-xl" style="background:#25D366;">Start Your First Journey →</a>
        @endif
      </div>

      <div class="relative">
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-2xl">
          <div class="flex items-center justify-between px-4 py-3 border-b border-white/[0.06]" style="background:#f8fbf9;">
            <div class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-full bg-red-500 opacity-70"></div>
              <div class="w-3 h-3 rounded-full bg-yellow-400 opacity-70"></div>
              <div class="w-3 h-3 rounded-full opacity-70" style="background:#25D366;"></div>
            </div>
            <p class="text-xs text-gray-500 font-medium">Sales Pipeline — Kanban</p>
            <div class="badge text-xs">Live</div>
          </div>
          <div class="px-4 py-4 grid grid-cols-4 gap-2">
            @foreach([
              ['Lead', '12', '#64748b'],
              ['Qualified', '8', '#2563eb'],
              ['Proposal', '5', '#d97706'],
              ['Won', '3', '#25D366'],
            ] as [$stage, $count, $color])
            <div class="rounded-xl border border-gray-200 p-2" style="background:#ffffff;">
              <div class="flex items-center justify-between mb-2">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">{{ $stage }}</p>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full text-gray-300" style="background:{{ $color }}33;color:{{ $color }};">{{ $count }}</span>
              </div>
              <div class="space-y-1.5">
                @for($i = 0; $i < min(2, (int) $count); $i++)
                <div class="rounded-lg px-2 py-1.5 border border-gray-200" style="background:#f3faf6;">
                  <p class="text-[10px] text-gray-900 font-medium">Contact {{ $i + 1 }}</p>
                  <p class="text-[9px] text-gray-500">Moved today</p>
                </div>
                @endfor
              </div>
            </div>
            @endforeach
          </div>
          <div class="px-4 py-2.5 border-t border-gray-200 flex items-center justify-between" style="background:#f8fbf9;">
            <p class="text-xs text-gray-500">Stage campaign queued on move</p>
            <div class="flex gap-2">
              <div class="badge text-xs">Templates ✓</div>
              <div class="badge text-xs">Kanban ✓</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== BOOKINGS SPOTLIGHT ===== -->
<section id="bookings" class="py-24 border-y border-gray-200" style="background:#f3faf6;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Bookings</div>
      <h2 class="font-display section-title font-800 mb-4">Book appointments.<br/><span class="grad-text">Run events. Reduce no-shows.</span></h2>
      <p class="body-lg text-gray-600 max-w-3xl mx-auto">The Bookings app inside ConvoConnect — multi-staff scheduling, public widgets, event registrations, Google Calendar sync, and WhatsApp reminders. Configure everything from one Bookings tab in Company Apps.</p>
    </div>

    <div class="mb-16">
      @include('wpsupportlanding::landing.partials.infographics.booking_lifecycle')
    </div>

    <!-- 3-step flow -->
    <div class="grid md:grid-cols-3 gap-4 mb-16">
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.1);">⚙️</div>
        <p class="font-display font-700 text-lg mb-2 text-gray-900">1. Set Up Services & Staff</p>
        <p class="text-base text-gray-600 leading-relaxed">Define bookable services, departments, working hours, and team members. Choose round-robin, least-busy, or customer-picks-staff assignment.</p>
      </div>
      <div class="card-lift border rounded-2xl p-6 text-center" style="background:rgba(37,211,102,0.04);border-color:rgba(37,211,102,0.2);">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.15);">🌐</div>
        <p class="font-display font-700 text-base mb-2" style="color:#25D366;">2. Share Your Booking Page</p>
        <p class="text-base text-gray-600 leading-relaxed">Embed a branded widget on your site or share a public booking link. Customers pick services, staff, and available slots — no app download needed.</p>
      </div>
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.1);">🔔</div>
        <p class="font-display font-700 text-lg mb-2 text-gray-900">3. Automate Reminders</p>
        <p class="text-base text-gray-600 leading-relaxed">Send WhatsApp utility-template reminders before and after appointments. Staff get notified on book, cancel, and reschedule — synced to Google Calendar.</p>
      </div>
    </div>

    <!-- Two-column detail -->
    <div class="grid lg:grid-cols-2 gap-12 items-center">
      <div>
        <h3 class="font-display text-2xl font-800 mb-6">Everything you need to manage bookings on WhatsApp</h3>
        <div class="space-y-4">
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">👥</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Multi-staff & department scheduling</p>
              <p class="text-sm text-gray-600">Assign services to teams, set per-staff hours, and handle holiday closures. Smart assignment modes distribute bookings fairly.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">🎟️</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Events with seat capacity</p>
              <p class="text-sm text-gray-600">Run workshops, classes, or webinars with multiple occurrences, party sizes, and registration management — all from one dashboard.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">💬</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Bookings sidebar in chat</p>
              <p class="text-sm text-gray-600">Agents see a contact's upcoming appointments and event registrations right in the inbox sidebar — open chat from any booking detail page.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">🔌</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">REST API with scoped keys</p>
              <p class="text-sm text-gray-600">Integrate with your CRM or website using slot-based booking API. Scoped public keys keep your main account secure.</p>
            </div>
          </div>
        </div>
        @if($registrationEnabled)
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 mt-8 px-5 py-3 text-sm font-semibold text-black rounded-xl" style="background:#25D366;">Start Booking on WhatsApp →</a>
        @endif
      </div>

      <!-- Booking widget mockup -->
      <div class="relative">
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-2xl">
          <div class="flex items-center justify-between px-4 py-3 border-b border-white/[0.06]" style="background:#f8fbf9;">
            <div class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-full bg-red-500 opacity-70"></div>
              <div class="w-3 h-3 rounded-full bg-yellow-400 opacity-70"></div>
              <div class="w-3 h-3 rounded-full opacity-70" style="background:#25D366;"></div>
            </div>
            <p class="text-xs text-gray-500 font-medium">Booking Widget — StyleHive Salon</p>
            <div class="badge text-xs">Live</div>
          </div>
          <div class="px-5 py-5 space-y-4">
            <div class="text-center mb-2">
              <p class="text-base font-semibold text-gray-900">Book an Appointment</p>
              <p class="text-xs text-gray-500">Select a service and available time</p>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div class="flow-node px-3 py-2.5 text-center" style="border-color:rgba(37,211,102,0.4);background:rgba(37,211,102,0.08);">
                <p class="text-xs font-semibold" style="color:#25D366;">Premium Cut</p>
                <p class="text-xs text-gray-500">45 min · KES 2,500</p>
              </div>
              <div class="flow-node px-3 py-2.5 text-center">
                <p class="text-xs font-semibold text-gray-900">Beard Trim</p>
                <p class="text-xs text-gray-500">20 min · KES 800</p>
              </div>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-2 uppercase tracking-wider" style="font-size:9px;">Available — Sat, Jun 21</p>
              <div class="grid grid-cols-3 gap-2">
                <div class="flow-node px-2 py-1.5 text-center text-xs text-gray-500">10:00</div>
                <div class="flow-node px-2 py-1.5 text-center text-xs font-semibold" style="color:#25D366;border-color:rgba(37,211,102,0.4);">14:00</div>
                <div class="flow-node px-2 py-1.5 text-center text-xs text-gray-500">16:30</div>
              </div>
            </div>
            <div class="flex items-center gap-3 px-3 py-2 rounded-xl" style="background:rgba(37,211,102,0.06);border:1px solid rgba(37,211,102,0.15);">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold" style="background:linear-gradient(135deg,#25D366,#075E54);">SM</div>
              <div>
                <p class="text-xs font-semibold text-gray-900">Sarah Mwangi</p>
                <p class="text-xs text-gray-500">Senior Stylist</p>
              </div>
            </div>
            <button class="w-full py-2.5 rounded-xl text-xs font-semibold text-black" style="background:#25D366;">Confirm Booking →</button>
          </div>
          <div class="px-4 py-2.5 border-t border-gray-200 flex items-center justify-between" style="background:#f8fbf9;">
            <p class="text-xs text-gray-500">Powered by ConvoConnect</p>
            <div class="flex gap-2">
              <div class="badge text-xs">Calendar ✓</div>
              <div class="badge text-xs">Reminders ✓</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== CATALOG SPOTLIGHT ===== -->
<section id="catalog" class="py-24 border-y border-gray-200" style="background:#f0faf7;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Product Catalog & Commerce</div>
      <h2 class="font-display section-title font-800 mb-4">Import products. Sell inside<br/><span class="grad-text">WhatsApp.</span></h2>
      <p class="body-lg text-gray-600 max-w-3xl mx-auto">Import from Excel, Shopify, or WooCommerce with live inventory sync. Share branded shop links, sell from the chat sidebar, and collect via invoice — with STK retry, Paystack fallback, and WhatsApp chase if a PIN times out.</p>
    </div>

    <!-- 3-step flow -->
    <div class="grid md:grid-cols-3 gap-4 mb-16">
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.1);">🛒</div>
        <p class="font-display font-700 text-lg mb-2 text-gray-900">1. Import Your Catalog</p>
        <p class="text-base text-gray-600 leading-relaxed">Import from Shopify, WooCommerce, Excel, or your own API. Ongoing store sync keeps prices and stock up to date automatically.</p>
      </div>
      <div class="card-lift border rounded-2xl p-6 text-center" style="background:rgba(37,211,102,0.04);border-color:rgba(37,211,102,0.2);">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.15);">⚡</div>
        <p class="font-display font-700 text-base mb-2" style="color:#25D366;">2. Sell from Chat or Flows</p>
        <p class="text-base text-gray-600 leading-relaxed">Send shop links or individual products from the inbox sidebar. Add catalog nodes to flows — checkout resumes the automation automatically.</p>
      </div>
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.1);">💳</div>
        <p class="font-display font-700 text-lg mb-2 text-gray-900">3. Invoice & Collect Payment</p>
        <p class="text-base text-gray-600 leading-relaxed">Generate a PDF invoice in the same conversation. If STK times out, ConvoConnect retries, offers Paystack, and chases on WhatsApp until the invoice is paid.</p>
      </div>
    </div>

    <!-- Two-column detail -->
    <div class="grid lg:grid-cols-2 gap-12 items-center">
      <!-- Left: features list -->
      <div>
        <h3 class="font-display text-2xl font-800 mb-6">Everything you need to run commerce on WhatsApp</h3>
        <div class="space-y-4">
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">📦</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Branded WhatsApp shops</p>
              <p class="text-sm text-gray-600">Create catalogs with images, variants, and collections. Share at /shop/yourbrand — customers browse and add to cart without leaving WhatsApp.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">💬</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Catalog sidebar in chat</p>
              <p class="text-sm text-gray-600">Search and send individual products or shop links directly from the inbox. Agents close sales without switching tools.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">🔄</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Live store sync & inventory</p>
              <p class="text-sm text-gray-600">Shopify and WooCommerce webhooks keep stock accurate. Inventory reservations prevent overselling during checkout.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">🧾</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Checkout &amp; collections</p>
              <p class="text-sm text-gray-600">Customers checkout via WhatsApp or an auto-generated invoice. Unpaid invoices are retried, offered Paystack, and chased — the flow resumes when payment lands on your keys.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">📊</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Analytics & A/B experiments</p>
              <p class="text-sm text-gray-600">Track views, cart adds, and checkouts. Run weighted catalog experiments to optimize which products convert best.</p>
            </div>
          </div>
        </div>
        @if($registrationEnabled)
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 mt-8 px-5 py-3 text-sm font-semibold text-black rounded-xl" style="background:#25D366;">Start Selling on WhatsApp →</a>
        @endif
      </div>

      <!-- Right: mockup -->
      <div class="relative">
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-2xl">
          <!-- Bar -->
          <div class="flex items-center justify-between px-4 py-3 border-b border-white/[0.06]" style="background:#f8fbf9;">
            <div class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-full bg-red-500 opacity-70"></div>
              <div class="w-3 h-3 rounded-full bg-yellow-400 opacity-70"></div>
              <div class="w-3 h-3 rounded-full opacity-70" style="background:#25D366;"></div>
            </div>
            <p class="text-xs text-gray-500 font-medium">Flow Builder — Product Order Flow</p>
            <div class="badge text-xs">Live</div>
          </div>
          <!-- Flow nodes -->
          <div class="px-6 py-5 space-y-0 flex flex-col items-center">
            <div class="flow-node px-4 py-3 w-full text-center">
              <div class="flex items-center gap-2 justify-center mb-0.5">
                <span class="text-base">💬</span>
                <p class="text-xs font-semibold text-gray-900">Keyword: "shop"</p>
              </div>
              <p class="text-xs text-gray-500">Trigger when customer types shop</p>
            </div>
            <div class="flow-line"></div>
            <div class="flow-node px-4 py-3 w-full text-center" style="border-color:rgba(37,211,102,0.3);">
              <div class="flex items-center gap-2 justify-center mb-0.5">
                <span class="text-base">🛒</span>
                <p class="text-xs font-semibold" style="color:#25D366;">Product Catalog</p>
              </div>
              <p class="text-xs text-gray-500">Show catalog · customer selects items</p>
            </div>
            <div class="flow-line"></div>
            <div class="flow-node px-4 py-3 w-full text-center">
              <div class="flex items-center gap-2 justify-center mb-0.5">
                <span class="text-base">🧾</span>
                <p class="text-xs font-semibold text-gray-900">Generate Invoice</p>
              </div>
              <p class="text-xs text-gray-500">Auto-create PDF invoice from order</p>
            </div>
            <div class="flow-line"></div>
            <div class="grid grid-cols-2 gap-3 w-full">
              <div class="flow-node px-3 py-3 text-center">
                <span class="text-sm">📱</span>
                <p class="text-xs font-semibold text-gray-900 mt-1">M-Pesa STK</p>
                <p class="text-xs text-gray-500">Retry on timeout</p>
              </div>
              <div class="flow-node px-3 py-3 text-center">
                <span class="text-sm">💳</span>
                <p class="text-xs font-semibold text-gray-900 mt-1">Paystack</p>
                <p class="text-xs text-gray-500">Fallback + chase</p>
              </div>
            </div>
            <div class="flow-line"></div>
            <div class="flow-node px-4 py-3 w-full text-center" style="background:rgba(37,211,102,0.08);border-color:rgba(37,211,102,0.3);">
              <div class="flex items-center gap-2 justify-center mb-0.5">
                <span class="text-base">✅</span>
                <p class="text-xs font-semibold" style="color:#25D366;">Payment Confirmed</p>
              </div>
              <p class="text-xs text-gray-500">Send receipt · update order status</p>
            </div>
          </div>
          <div class="px-4 py-2.5 border-t border-gray-200 flex items-center justify-between" style="background:#f8fbf9;">
            <p class="text-xs text-gray-500">Drag to add more nodes</p>
            <div class="flex gap-2">
              <div class="badge text-xs">Catalog ✓</div>
              <div class="badge text-xs">Collections ✓</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== COLLECTIONS SPOTLIGHT ===== -->
<section id="collections" class="py-24 border-y border-gray-200" style="background:#f7f4ff;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Collections</div>
      <h2 class="font-display section-title font-800 mb-4">Ask. Retry. Fall back.<br/><span class="grad-text">Chase until paid.</span></h2>
      <p class="body-lg text-gray-600 max-w-3xl mx-auto">Conversation to catalog or booking to invoice to STK. If the PIN times out, ConvoConnect retries on your Daraja keys, offers Paystack, then chases on WhatsApp. Money never sits in our wallet — settlement stays on your keys.</p>
    </div>

    <div class="grid md:grid-cols-3 gap-4 mb-16">
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.1);">📱</div>
        <p class="font-display font-700 text-lg mb-2 text-gray-900">1. Request payment</p>
        <p class="text-base text-gray-600 leading-relaxed">Send M-Pesa STK from catalog checkout, a booking, a flow, or the inbox. The invoice stays open until the customer enters a PIN or the attempt times out.</p>
      </div>
      <div class="card-lift border rounded-2xl p-6 text-center" style="background:rgba(37,211,102,0.04);border-color:rgba(37,211,102,0.2);">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.15);">🔁</div>
        <p class="font-display font-700 text-base mb-2" style="color:#25D366;">2. STK retry &amp; Paystack fallback</p>
        <p class="text-base text-gray-600 leading-relaxed">If the PIN prompt expires, retry STK on the same merchant keys, then offer Paystack card checkout so the sale is not lost to a single missed prompt.</p>
      </div>
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4" style="background:rgba(37,211,102,0.1);">💬</div>
        <p class="font-display font-700 text-lg mb-2 text-gray-900">3. WhatsApp chase</p>
        <p class="text-base text-gray-600 leading-relaxed">Unpaid invoices move to chasing. WhatsApp reminders keep the thread alive until paid, then the flow resumes and the collections board clears the row.</p>
      </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-12 items-center">
      <div>
        <h3 class="font-display text-2xl font-800 mb-6">A collections board, not a payment wallet</h3>
        <div class="space-y-4">
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">🔑</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Your Daraja and Paystack keys</p>
              <p class="text-sm text-gray-600">ConvoConnect orchestrates STK, retries, fallback, and chase. Settlement lands on the merchant account you already connected — we are not a PSP.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">📋</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Open-invoice board</p>
              <p class="text-sm text-gray-600">See due, PIN pending, failed, chasing, and unmatched invoices in one list. Retry STK or match a receipt without leaving the dashboard.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">🛒</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Same path as Cart Recovery, Booking Convert, and Lead-to-Cash</p>
              <p class="text-sm text-gray-600">Catalog carts, bookings, and flow invoices all enter the same collection engine — so outcome playbooks close on collected cash, not a one-shot STK.</p>
            </div>
          </div>
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base" style="background:rgba(37,211,102,0.1);">⚡</div>
            <div>
              <p class="text-base font-semibold text-gray-900 mb-0.5">Flows wait for a terminal result</p>
              <p class="text-sm text-gray-600">Automation does not treat a timed-out PIN as a failed sale. The flow resumes when the invoice is paid, fulfilled, closed, or cancelled.</p>
            </div>
          </div>
        </div>
        @if($registrationEnabled)
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 mt-8 px-5 py-3 text-sm font-semibold text-black rounded-xl" style="background:#25D366;">Start collecting on WhatsApp →</a>
        @endif
      </div>

      <div class="relative">
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-2xl">
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200" style="background:#f8fbf9;">
            <div>
              <p class="text-sm font-semibold text-gray-900">Collections board</p>
              <p class="text-xs text-gray-500">3 open · merchant keys</p>
            </div>
            <div class="badge text-xs">Live</div>
          </div>
          <div class="divide-y divide-gray-100">
            @foreach([
              ['INV-1042', 'Amina K.', 'KES 4,500', 'PIN pending', 'Retry STK in 8m', '#ca8a04'],
              ['INV-1041', 'Brian O.', 'KES 12,000', 'Chasing', 'WhatsApp reminder queued', '#7c3aed'],
              ['INV-1038', 'Wanjiku M.', 'KES 2,800', 'Paid', 'Flow resumed', '#16a34a'],
            ] as [$number, $name, $amount, $status, $next, $color])
            <div class="px-4 py-3 flex items-center justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-gray-900">{{ $number }}</p>
                <p class="text-xs text-gray-500">{{ $name }} · {{ $amount }}</p>
              </div>
              <div class="text-right">
                <span class="inline-flex text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full" style="background:{{ $color }}1a;color:{{ $color }};">{{ $status }}</span>
                <p class="text-[11px] text-gray-500 mt-1">{{ $next }}</p>
              </div>
            </div>
            @endforeach
          </div>
          <div class="px-4 py-2.5 border-t border-gray-200 flex items-center justify-between" style="background:#f8fbf9;">
            <p class="text-xs text-gray-500">Retry · Paystack fallback · chase</p>
            <div class="badge text-xs">Not a wallet</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== AUTOMATION SECTION ===== -->
<section id="automation" class="py-24 relative overflow-hidden" style="background:#f3faf6;">
  <div class="absolute inset-0 grid-pattern"></div>
  <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-px" style="background:linear-gradient(90deg,transparent,rgba(37,211,102,0.3),transparent);"></div>
  <div class="relative z-10 max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Workflow Automation</div>
      <h2 class="font-display section-title font-800 mb-4">Orchestrate every<br/>customer touchpoint</h2>
      <p class="body-lg text-gray-600 max-w-3xl mx-auto">Build sales, support, and onboarding flows visually — start from curated templates, convert WhatsApp Forms into automations, or describe what you need and let the AI Flow Assistant draft it for you.</p>
    </div>

    <div class="mb-16">
      @include('wpsupportlanding::landing.partials.infographics.automation_nodes')
    </div>

    <div class="grid lg:grid-cols-2 gap-12 items-center">
      <!-- Flow diagram -->
      <div class="space-y-0">
        <div class="flex flex-col items-center">
          <div class="flow-node px-5 py-3 w-64 text-center">
            <div class="flex items-center gap-2 justify-center mb-1">
              <span class="text-lg">⚡</span>
              <p class="text-base font-semibold text-gray-900">Trigger Node</p>
            </div>
            <p class="text-xs text-gray-500">Keyword "book" received</p>
          </div>
          <div class="flow-line"></div>
          <div class="flow-node px-5 py-3 w-64 text-center">
            <div class="flex items-center gap-2 justify-center mb-1">
              <span class="text-lg">💬</span>
              <p class="text-base font-semibold text-gray-900">Send Message</p>
            </div>
            <p class="text-xs text-gray-500">"Hi! Ready to book? Fill our form 👇"</p>
          </div>
          <div class="flow-line"></div>
          <div class="flow-node px-5 py-3 w-64 text-center" style="border-color:rgba(37,211,102,0.35);">
            <div class="flex items-center gap-2 justify-center mb-1">
              <span class="text-lg">📋</span>
              <p class="text-sm font-semibold" style="color:#25D366;">WhatsApp Flow</p>
            </div>
            <p class="text-xs text-gray-500">Open booking widget in-chat</p>
          </div>
          <div class="flow-line"></div>
          <div class="grid grid-cols-2 gap-4 w-full max-w-sm">
            <div class="flow-node px-3 py-3 text-center">
              <span class="text-sm">✅</span>
              <p class="text-xs font-semibold text-gray-900 mt-1">Confirmed</p>
              <p class="text-xs text-gray-500">Send receipt</p>
            </div>
            <div class="flow-node px-3 py-3 text-center">
              <span class="text-sm">🔁</span>
              <p class="text-xs font-semibold text-gray-900 mt-1">Retry</p>
              <p class="text-xs text-gray-500">Send reminder</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Node types -->
      <div class="space-y-3">
        <h3 class="font-display font-700 text-xl mb-4 text-gray-900">38+ automation nodes</h3>
        <div class="flow-node px-4 py-3.5 flex items-start gap-4">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background:rgba(37,211,102,0.1);">⚡</div>
          <div>
            <p class="text-base font-semibold text-gray-900 mb-0.5">Triggers &amp; branching</p>
            <p class="text-xs text-gray-500">Keywords, inbound messages, form submissions, waits, conditions, HTTP/webhooks, and scheduled steps.</p>
          </div>
        </div>
        <div class="flow-node px-4 py-3.5 flex items-start gap-4">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background:rgba(37,211,102,0.1);">💬</div>
          <div>
            <p class="text-base font-semibold text-gray-900 mb-0.5">Messaging &amp; WhatsApp Forms</p>
            <p class="text-xs text-gray-500">Text, media, lists, templates, questions, and native WhatsApp Flow forms with data exchange.</p>
          </div>
        </div>
        <div class="flow-node px-4 py-3.5 flex items-start gap-4">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background:rgba(37,211,102,0.1);">🛍️</div>
          <div>
            <p class="text-base font-semibold text-gray-900 mb-0.5">Commerce &amp; payments</p>
            <p class="text-xs text-gray-500">Catalogs, order status, pricing, M-Pesa and payment requests that resume the flow on success.</p>
          </div>
        </div>
        <div class="flow-node px-4 py-3.5 flex items-start gap-4">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background:rgba(37,211,102,0.1);">📅</div>
          <div>
            <p class="text-base font-semibold text-gray-900 mb-0.5">Bookings &amp; events</p>
            <p class="text-xs text-gray-500">Appointment and event nodes that collect availability, confirm slots, and sync with Google Calendar.</p>
          </div>
        </div>
        <div class="flow-node px-4 py-3.5 flex items-start gap-4">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background:rgba(37,211,102,0.1);">🗂️</div>
          <div>
            <p class="text-base font-semibold text-gray-900 mb-0.5">CRM &amp; assignment</p>
            <p class="text-xs text-gray-500">Update contacts, assign agents or groups, enroll journeys, and write to your flow datastore.</p>
          </div>
        </div>
        <div class="flow-node px-4 py-3.5 flex items-start gap-4">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background:rgba(37,211,102,0.1);">🤖</div>
          <div>
            <p class="text-base font-semibold text-gray-900 mb-0.5">AI Flow Assistant &amp; AI nodes</p>
            <p class="text-xs text-gray-500">Draft flows in natural language with managed AI credits, then run knowledge-based AI steps with human handover.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== INTEGRATIONS ===== -->
<section id="integrations" class="py-24 relative">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Integrations</div>
      <h2 class="font-display section-title font-800 mb-4">Connects with<br/>your stack</h2>
      <p class="body-lg text-gray-600 max-w-3xl mx-auto">Connect the tools that power selling and outreach — Meta Embedded Signup for WhatsApp, Instagram &amp; Messenger, store sync, commerce payments, ConvoConnect SMS with Sender ID, email, and an embeddable WhatsApp widget.</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-12">
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(149,191,71,0.1);" aria-hidden="true">🛍️</div>
        <div><p class="text-base font-semibold text-gray-900">Shopify</p><p class="text-xs text-gray-500 mt-0.5">Products, orders &amp; webhooks</p></div>
      </div>
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(150,88,183,0.1);" aria-hidden="true">🛒</div>
        <div><p class="text-base font-semibold text-gray-900">WooCommerce</p><p class="text-xs text-gray-500 mt-0.5">Products, orders &amp; webhooks</p></div>
      </div>
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(0,150,64,0.1);" aria-hidden="true">📱</div>
        <div><p class="text-base font-semibold text-gray-900">M-Pesa</p><p class="text-xs text-gray-500 mt-0.5">STK retry on merchant keys</p></div>
      </div>
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(0,174,239,0.1);" aria-hidden="true">💳</div>
        <div><p class="text-base font-semibold text-gray-900">Paystack</p><p class="text-xs text-gray-500 mt-0.5">Fallback checkout on invoices</p></div>
      </div>
      <!-- <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(99,91,255,0.1);" aria-hidden="true">💳</div>
        <div><p class="text-base font-semibold text-gray-900">Stripe</p><p class="text-xs text-gray-500 mt-0.5">SaaS plan subscriptions</p></div>
      </div> -->
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(245,47,47,0.1);" aria-hidden="true">📨</div>
        <div><p class="text-base font-semibold text-gray-900">ConvoConnect SMS</p><p class="text-xs text-gray-500 mt-0.5">Sender ID · KES 0.6 / SMS</p></div>
      </div>
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(37,99,235,0.1);" aria-hidden="true">📧</div>
        <div><p class="text-base font-semibold text-gray-900">SMTP Email</p><p class="text-xs text-gray-500 mt-0.5">Email campaigns &amp; notices</p></div>
      </div>
      <div class="integration-card rounded-2xl p-5 flex flex-col items-center text-center gap-2.5">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" style="background:rgba(24,119,242,0.1);" aria-hidden="true">🔵</div>
        <div><p class="text-base font-semibold text-gray-900">Meta Embedded</p><p class="text-xs text-gray-500 mt-0.5">WhatsApp · Instagram · Messenger</p></div>
      </div>
    </div>

    <!-- Widget code snippet -->
    <div class="max-w-2xl mx-auto">
      <p class="text-sm text-gray-400 mb-3 font-medium">Add the WhatsApp chat widget with one script tag:</p>
      <div class="code-block p-4 overflow-x-auto">
        <pre class="text-sm"><code><span style="color:#6a9955;">// Paste before &lt;/body&gt; — widget ID from WhatsApp Widget settings</span>
<span style="color:#569cd6;">&lt;script</span> <span style="color:#9cdcfe;">src</span><span style="color:#d4d4d4;">=</span><span style="color:#ce9178;">"{{ url('/popup/whatsapp') }}?id=YOUR_WIDGET_ID"</span><span style="color:#569cd6;">&gt;&lt;/script&gt;</span></code></pre>
      </div>
    </div>
  </div>
</section>

<!-- ===== API SECTION ===== -->
<section id="api" class="py-24 relative overflow-hidden" style="background:#eef8f3;">
  <div class="absolute inset-0 grid-pattern opacity-60"></div>
  <div class="absolute top-0 left-0 right-0 h-px" style="background:linear-gradient(90deg,transparent,rgba(37,211,102,0.2),transparent);"></div>
  <div class="relative z-10 max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Developer API</div>
      <h2 class="font-display section-title font-800 mb-4">Automate with<br/><span class="grad-text">developer APIs</span></h2>
      <p class="body-lg text-gray-600 max-w-3xl mx-auto">Send WhatsApp messages, manage contacts, trigger campaigns, sync catalogs, and collect invoice payments programmatically with plan-gated API access.</p>
    </div>

    <div class="grid lg:grid-cols-2 gap-12 items-start">
      <!-- Endpoints list -->
      <div class="space-y-2">
        <h3 class="font-display font-700 text-lg mb-5 text-gray-900">Available endpoints</h3>
        <div class="space-y-2">
          <a href="{{ url('/api/v1/docs') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200 hover:border-green-300 transition-colors">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(59,130,246,0.15);color:#60a5fa;">GET</span>
            <code class="text-base text-gray-700">/api/v1/docs</code>
            <span class="text-xs text-gray-500 ml-auto">OpenAPI docs</span>
          </a>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-base text-gray-700">/api/v1/messages</code>
            <span class="text-xs text-gray-500 ml-auto">WhatsApp + SMS</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-base text-gray-700">/api/wpbox/sendmessage</code>
            <span class="text-xs text-gray-500 ml-auto">Legacy alias</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-base text-gray-700">/api/wpbox/sendtemplatemessage</code>
            <span class="text-xs text-gray-500 ml-auto">Send template</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(59,130,246,0.15);color:#60a5fa;">GET</span>
            <code class="text-base text-gray-700">/api/v1/contacts</code>
            <span class="text-xs text-gray-500 ml-auto">Paginated contacts</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(59,130,246,0.15);color:#60a5fa;">GET</span>
            <code class="text-base text-gray-700">/api/v1/conversations</code>
            <span class="text-xs text-gray-500 ml-auto">Inbox API</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-base text-gray-700">/api/v1/events</code>
            <span class="text-xs text-gray-500 ml-auto">Store events</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-base text-gray-700">/api/v1/webhooks</code>
            <span class="text-xs text-gray-500 ml-auto">Signed webhooks</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-base text-gray-700">/api/v1/bookings</code>
            <span class="text-xs text-gray-500 ml-auto">Create booking</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-base text-gray-700">/api/v1/invoices</code>
            <span class="text-xs text-gray-500 ml-auto">Create + send invoice</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-base text-gray-700">/api/wpbox/makeContact</code>
            <span class="text-xs text-gray-500 ml-auto">Create contact</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-base text-gray-700">/api/wpbox/sendcampaigns</code>
            <span class="text-xs text-gray-500 ml-auto">Trigger campaign</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(59,130,246,0.15);color:#60a5fa;">GET</span>
            <code class="text-base text-gray-700">/api/v1/catalog/catalogs</code>
            <span class="text-xs text-gray-500 ml-auto">List catalogs</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-base text-gray-700">/api/v1/catalog/catalogs/{id}/sync</code>
            <span class="text-xs text-gray-500 ml-auto">Sync catalog</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(37,211,102,0.15);color:#25D366;">POST</span>
            <code class="text-base text-gray-700">/api/invoice/{uuid}/pay</code>
            <span class="text-xs text-gray-500 ml-auto">Initiate payment</span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background:rgba(59,130,246,0.15);color:#60a5fa;">GET</span>
            <code class="text-base text-gray-700">/api/wpbox/me</code>
            <span class="text-xs text-gray-500 ml-auto">API account info</span>
          </div>
        </div>
        <div class="pt-4 flex flex-wrap items-center gap-3">
          @if($registrationEnabled)
          <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-black rounded-xl" style="background:#25D366;">Get Your API Key →</a>
          @endif
          <a href="{{ url('/api/v1/docs') }}" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-gray-800 rounded-xl bg-white border border-gray-200">View API docs →</a>
        </div>
      </div>

      <!-- Code snippet -->
      <div>
        <h3 class="font-display font-700 text-lg mb-5 text-gray-900">Send a message in seconds</h3>
        <div class="code-block p-5 overflow-x-auto">
          <pre class="text-sm leading-relaxed"><code><span style="color:#569cd6;">const</span> <span style="color:#9cdcfe;">response</span> <span style="color:#d4d4d4;">= </span><span style="color:#569cd6;">await</span> <span style="color:#dcdcaa;">fetch</span><span style="color:#d4d4d4;">(</span>
  <span style="color:#ce9178;">"{{ url('/api/v1/messages') }}"</span><span style="color:#d4d4d4;">,</span>
  <span style="color:#d4d4d4;">{</span>
    <span style="color:#9cdcfe;">method</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"POST"</span><span style="color:#d4d4d4;">,</span>
    <span style="color:#9cdcfe;">headers</span><span style="color:#d4d4d4;">: {</span>
      <span style="color:#ce9178;">"Authorization"</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">`Bearer ${API_KEY}`</span><span style="color:#d4d4d4;">,</span>
      <span style="color:#ce9178;">"X-Company-Id"</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"1"</span><span style="color:#d4d4d4;">,</span>
      <span style="color:#ce9178;">"Idempotency-Key"</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"msg-001"</span><span style="color:#d4d4d4;">,</span>
      <span style="color:#ce9178;">"Content-Type"</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"application/json"</span>
    <span style="color:#d4d4d4;">},</span>
    <span style="color:#9cdcfe;">body</span><span style="color:#d4d4d4;">: </span><span style="color:#dcdcaa;">JSON</span><span style="color:#d4d4d4;">.</span><span style="color:#dcdcaa;">stringify</span><span style="color:#d4d4d4;">({</span>
      <span style="color:#9cdcfe;">channel</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"whatsapp"</span><span style="color:#d4d4d4;">,</span>
      <span style="color:#9cdcfe;">to</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"254700000000"</span><span style="color:#d4d4d4;">,</span>
      <span style="color:#9cdcfe;">body</span><span style="color:#d4d4d4;">: </span><span style="color:#ce9178;">"Hello from {{ $siteName }}!"</span>
    <span style="color:#d4d4d4;">})</span>
  <span style="color:#d4d4d4;">}</span>
<span style="color:#d4d4d4;">);</span>

<span style="color:#569cd6;">const</span> <span style="color:#9cdcfe;">data</span> <span style="color:#d4d4d4;">= </span><span style="color:#569cd6;">await</span> <span style="color:#9cdcfe;">response</span><span style="color:#d4d4d4;">.</span><span style="color:#dcdcaa;">json</span><span style="color:#d4d4d4;">();</span>
<span style="color:#dcdcaa;">console</span><span style="color:#d4d4d4;">.</span><span style="color:#dcdcaa;">log</span><span style="color:#d4d4d4;">(</span><span style="color:#9cdcfe;">data</span><span style="color:#d4d4d4;">);</span></code></pre>
        </div>
        <div class="mt-4 flex items-center gap-3 p-3 rounded-xl" style="background:rgba(37,211,102,0.05);border:1px solid rgba(37,211,102,0.1);">
          <div class="w-2 h-2 rounded-full flex-shrink-0" style="background:#25D366;"></div>
          <p class="text-xs text-gray-400">Plan-gated API with signed, retried webhooks. Docs: <a href="{{ url('/api/v1/docs') }}" class="underline">/api/v1/docs</a>. Broadcasts capped at 10 WhatsApp messages/second.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== PRICING ===== -->
<section id="pricing" class="py-24">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-12">
      <div class="badge inline-flex mb-4">Pricing</div>
      <h2 class="font-display section-title font-800 mb-4">Simple, transparent pricing</h2>
      <p class="text-gray-400">No surprise bills. Pick a plan, connect your WhatsApp number, and grow.</p>
    </div>

    @if(isset($plans) && $plans->count() > 0)
    @php $planCount = $plans->count(); @endphp
    <div class="grid gap-4 {{ $planCount === 1 ? 'max-w-sm mx-auto' : ($planCount === 2 ? 'md:grid-cols-2 max-w-2xl mx-auto' : 'md:grid-cols-3') }}">
      @foreach($plans as $loopIndex => $plan)
      @php
        $isPopular = $planCount >= 3 && $loopIndex === intval(floor($planCount / 2));
        $currencySymbol = config('money')[strtoupper(config('settings.cashier_currency', 'usd'))]['symbol'] ?? '$';
        $periodLabel = ($plan->period == 1) ? 'month' : 'year';
        $features = array_filter(array_map('trim', explode(',', $plan->features ?? '')));
      @endphp

      @if($isPopular)
      <div class="pricing-popular card-lift rounded-2xl p-7 relative">
        <div class="absolute -top-3 left-1/2 -translate-x-1/2">
          <span class="badge font-semibold" style="background:rgba(37,211,102,0.2);">Most Popular</span>
        </div>
        <div class="mb-6">
          <p class="text-sm mb-1 font-medium" style="color:#25D366;">{{ $plan->name }}</p>
          <div class="flex items-baseline gap-1">
            <span class="text-gray-500 text-lg font-semibold self-start mt-1">{{ $currencySymbol }}</span>
            <span class="font-display text-4xl font-800 text-gray-900">{{ number_format($plan->price, 0) }}</span>
            <span class="text-gray-600 text-sm">/ {{ $periodLabel }}</span>
          </div>
          @if($plan->description)
          <p class="text-xs text-gray-500 mt-2 leading-relaxed">{{ $plan->description }}</p>
          @endif
        </div>
        @if($registrationEnabled)
        <a href="{{ route('register') }}" class="block w-full text-center py-2.5 rounded-xl text-sm font-semibold text-black transition-all hover:opacity-90 mb-6" style="background:#25D366;">Get Started</a>
        @endif
        @if(count($features) > 0)
        <ul class="space-y-2.5">
          @foreach($features as $feature)
          <li class="flex items-center gap-2.5 text-base text-gray-700">
            <svg class="w-4 h-4 flex-shrink-0" style="color:#25D366;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ $feature }}
          </li>
          @endforeach
        </ul>
        @endif
        <p class="text-xs text-gray-500 mt-4 text-center">No contracts · Cancel anytime</p>
      </div>
      @else
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-7">
        <div class="mb-6">
          <p class="text-sm text-gray-600 mb-1 font-medium">{{ $plan->name }}</p>
          <div class="flex items-baseline gap-1">
            <span class="text-gray-500 text-lg font-semibold self-start mt-1">{{ $currencySymbol }}</span>
            <span class="font-display text-4xl font-800 text-gray-900">{{ number_format($plan->price, 0) }}</span>
            <span class="text-gray-500 text-sm">/ {{ $periodLabel }}</span>
          </div>
          @if($plan->description)
          <p class="text-xs text-gray-500 mt-2 leading-relaxed">{{ $plan->description }}</p>
          @endif
        </div>
        @if($registrationEnabled)
        <a href="{{ route('register') }}" class="block w-full text-center py-2.5 rounded-xl border border-gray-200 text-base font-semibold text-gray-900 hover:bg-white transition-all mb-6">Get Started</a>
        @endif
        @if(count($features) > 0)
        <ul class="space-y-2.5">
          @foreach($features as $feature)
          <li class="flex items-center gap-2.5 text-sm text-gray-400">
            <svg class="w-4 h-4 flex-shrink-0" style="color:#25D366;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ $feature }}
          </li>
          @endforeach
        </ul>
        @endif
        <p class="text-xs text-gray-500 mt-4 text-center">No contracts · Cancel anytime</p>
      </div>
      @endif

      @endforeach
    </div>
    @else
    <p class="text-center text-gray-600 text-sm">No pricing plans available at this time.</p>
    @endif
  </div>
</section>

<!-- ===== TESTIMONIALS ===== -->
<section class="py-24 border-t border-gray-200" style="background:#f3faf6;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">Testimonials</div>
      <h2 class="font-display section-title font-800 text-gray-900">Loved by businesses<br/>across Africa & beyond</h2>
    </div>
    <div class="grid md:grid-cols-3 gap-4">
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="flex text-yellow-400 mb-4 text-sm">★★★★★</div>
        <p class="text-gray-700 text-sm leading-relaxed mb-5">"The new booking widgets cut our no-show rate in half. Customers book directly from WhatsApp, get automatic reminders, and our stylists see everything synced to Google Calendar. Game changer for salons."</p>
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full flex items-center justify-center font-semibold text-sm" style="background:linear-gradient(135deg,#25D366,#075E54);">AM</div>
          <div><p class="text-base font-semibold text-gray-900">Amina Mwangi</p><p class="text-xs text-gray-500">CEO, StyleHive Kenya</p></div>
        </div>
      </div>
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="flex text-yellow-400 mb-4 text-sm">★★★★★</div>
        <p class="text-gray-700 text-sm leading-relaxed mb-5">"File broadcasts with per-row template variables saved us hours. We upload a CSV, map columns to contact fields, and reach 10,000 customers with personalized messages — pause and resume whenever we need."</p>
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full flex items-center justify-center font-semibold text-sm" style="background:linear-gradient(135deg,#4facfe,#00f2fe);">DO</div>
          <div><p class="text-base font-semibold text-gray-900">David Okonkwo</p><p class="text-xs text-gray-500">Founder, WhatsApp Agency (Lagos)</p></div>
        </div>
      </div>
      <div class="card-lift bg-white border border-gray-200 rounded-2xl p-6">
        <div class="flex text-yellow-400 mb-4 text-sm">★★★★★</div>
        <p class="text-gray-700 text-sm leading-relaxed mb-5">"We sell products straight from the chat sidebar now — search catalog, send a product link, customer checks out on WhatsApp. Replaced our separate e-commerce chat tool entirely."</p>
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full flex items-center justify-center font-semibold text-sm" style="background:linear-gradient(135deg,#667eea,#764ba2);">TN</div>
          <div><p class="text-base font-semibold text-gray-900">Taiwo Nwosu</p><p class="text-xs text-gray-500">Head of CX, Datalink Partner</p></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FAQ ===== -->
<section id="faq" class="py-24 border-t border-gray-200">
  <div class="max-w-4xl mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-16">
      <div class="badge inline-flex mb-4">FAQ</div>
      <h2 class="font-display section-title font-800 text-gray-900">Common questions</h2>
    </div>

    <div class="space-y-2">
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 1 ? null : 1" :aria-expanded="(faqOpen === 1).toString()" aria-controls="faq-panel-1" id="faq-button-1">
          <span class="text-lg font-semibold text-gray-900">Do I need a WhatsApp Business API account to use ConvoConnect?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 1 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-1" x-show="faqOpen === 1" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-1">
          <p class="text-base text-gray-600 leading-relaxed">No — ConvoConnect is built on the official Meta WhatsApp Business Cloud API. Our Activation OS walks you through Embedded Signup, team setup, and your first flow — most businesses send their first reply within 15 minutes.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 2 ? null : 2" :aria-expanded="(faqOpen === 2).toString()" aria-controls="faq-panel-2" id="faq-button-2">
          <span class="text-lg font-semibold text-gray-900">Can I connect multiple WhatsApp numbers to one account?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 2 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-2" x-show="faqOpen === 2" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-2">
          <p class="text-base text-gray-600 leading-relaxed">Yes — plan limits determine how many WhatsApp numbers you can connect. Each number has its own inbox, flows, campaigns, and contacts, and you manage everything from a single dashboard. Check the pricing section for current plan entitlements.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 3 ? null : 3" :aria-expanded="(faqOpen === 3).toString()" aria-controls="faq-panel-3" id="faq-button-3">
          <span class="text-lg font-semibold text-gray-900">What exactly are WhatsApp Flows — are they different from chatbots?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 3 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-3" x-show="faqOpen === 3" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-3">
          <p class="text-base text-gray-600 leading-relaxed">Yes — completely different. WhatsApp Flows are interactive, multi-screen forms that open inside WhatsApp itself (like an app within WhatsApp). They support text inputs, dropdowns, radio buttons, date pickers, checkboxes, and opt-ins. Our Flowmaker automations are the chatbot/workflow engine that decides when to send a message or trigger a WhatsApp Flow.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 4 ? null : 4" :aria-expanded="(faqOpen === 4).toString()" aria-controls="faq-panel-4" id="faq-button-4">
          <span class="text-lg font-semibold text-gray-900">Which integrations are currently available?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 4 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-4" x-show="faqOpen === 4" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-4">
          <p class="text-base text-gray-600 leading-relaxed">We support Shopify and WooCommerce catalog sync, M-Pesa and Paystack for commerce payments, Stripe for SaaS subscriptions, Google Calendar for bookings, ConvoConnect SMS (optional Sender ID, KES 0.6 per SMS), SMTP email, Meta Embedded Signup, and our embeddable WhatsApp chat widget. Messaging and catalog APIs plus payment webhooks let you build custom integrations.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 5 ? null : 5" :aria-expanded="(faqOpen === 5).toString()" aria-controls="faq-panel-5" id="faq-button-5">
          <span class="text-lg font-semibold text-gray-900">Do you support M-Pesa payments?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 5 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-5" x-show="faqOpen === 5" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-5">
          <p class="text-base text-gray-600 leading-relaxed">Yes. Native M-Pesa STK push collects on your Daraja keys for Kenya and East Africa. If a PIN times out, ConvoConnect retries STK, then offers Paystack as a fallback and chases unpaid invoices on WhatsApp. Paid status shows on the collections board and can resume automation flows. Money stays on your keys — ConvoConnect is not a wallet or PSP. Stripe is used for ConvoConnect SaaS subscriptions, not in-chat commerce checkout.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 6 ? null : 6" :aria-expanded="(faqOpen === 6).toString()" aria-controls="faq-panel-6" id="faq-button-6">
          <span class="text-lg font-semibold text-gray-900">Is there a free trial? Do I need a credit card?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 6 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-6" x-show="faqOpen === 6" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-6">
          <p class="text-base text-gray-600 leading-relaxed">You can create an account and explore available plans in the pricing section. Features, messaging credits, managed AI credits, and seat limits depend on the plan you choose — upgrade anytime as you need campaigns, catalog commerce, or higher limits.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 7 ? null : 7" :aria-expanded="(faqOpen === 7).toString()" aria-controls="faq-panel-7" id="faq-button-7">
          <span class="text-lg font-semibold text-gray-900">How is ConvoConnect different from Meta's Business Agent?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 7 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-7" x-show="faqOpen === 7" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-7">
          <p class="text-base text-gray-600 leading-relaxed">Meta's Business Agent handles simple FAQ support. ConvoConnect is a full social commerce platform: sell from catalog sidebars and branded shops, run journey pipelines and multichannel campaigns (WhatsApp, SMS, email), take bookings, manage a Customer 360 inbox with knowledge-based Copilot suggestions, collect M-Pesa and Paystack payments, and track revenue — with AI Flow Assistant and AI nodes as tools you control, not a black-box replacement for your stack.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 8 ? null : 8" :aria-expanded="(faqOpen === 8).toString()" aria-controls="faq-panel-8" id="faq-button-8">
          <span class="text-lg font-semibold text-gray-900">How does the booking system work?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 8 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-8" x-show="faqOpen === 8" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-8">
          <p class="text-base text-gray-600 leading-relaxed">Set up bookable services, departments, and staff with working hours. Share a public booking page or embed a widget on your website. Customers pick services, staff, and available time slots. Bookings sync to Google Calendar, appear in the chat sidebar, and trigger automated WhatsApp reminder campaigns. You can also run events with seat capacity and registrations.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 9 ? null : 9" :aria-expanded="(faqOpen === 9).toString()" aria-controls="faq-panel-9" id="faq-button-9">
          <span class="text-lg font-semibold text-gray-900">What campaign broadcast types are available?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 9 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-9" x-show="faqOpen === 9" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-9">
          <p class="text-base text-gray-600 leading-relaxed">Campaigns run across WhatsApp, ConvoConnect SMS, and email. SMS includes optional Sender ID registration and transparent pricing at KES 0.6 per SMS. Audience modes include <strong class="text-gray-800">file</strong> (CSV/Excel with variables), <strong class="text-gray-800">group</strong> (contact groups or subscribers), and <strong class="text-gray-800">quick</strong> (pasted phone lists). All support segments, scheduling, pause/resume, delivery analytics, and API-triggered sends.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 10 ? null : 10" :aria-expanded="(faqOpen === 10).toString()" aria-controls="faq-panel-10" id="faq-button-10">
          <span class="text-lg font-semibold text-gray-900">Can I sell products directly in WhatsApp chat?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 10 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-10" x-show="faqOpen === 10" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-10">
          <p class="text-base text-gray-600 leading-relaxed">Yes. Build product catalogs and branded shop pages, then sell from the inbox catalog sidebar — search products and send shop links without leaving the conversation. Catalogs sync from Shopify, WooCommerce, Excel, or your own API. Customers checkout via WhatsApp or a public invoice. If STK is not completed, collections retry, fall back to Paystack, and chase until paid — then sync back to your CRM.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 11 ? null : 11" :aria-expanded="(faqOpen === 11).toString()" aria-controls="faq-panel-11" id="faq-button-11">
          <span class="text-lg font-semibold text-gray-900">What are flow templates and the AI Flow Assistant?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 11 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-11" x-show="faqOpen === 11" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-11">
          <p class="text-base text-gray-600 leading-relaxed">Flow templates are pre-built automations for common use cases — payment reminders, booking confirmations, support triage, and more. Install one in a click, then customize. The AI Flow Assistant lets you describe what you want in plain language and generates a draft flow you review and publish — no blank canvas required.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 12 ? null : 12" :aria-expanded="(faqOpen === 12).toString()" aria-controls="faq-panel-12" id="faq-button-12">
          <span class="text-lg font-semibold text-gray-900">Can I invite managers without giving them full owner access?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 12 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-12" x-show="faqOpen === 12" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-12">
          <p class="text-base text-gray-600 leading-relaxed">Yes. Organization managers get scoped access to specific modules — like inbox, campaigns, or flows — without billing or WhatsApp setup permissions. They see Customer 360, Copilot suggestions, and revenue widgets based on what you grant them. Owners keep control of activation, integrations, and account settings.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 13 ? null : 13" :aria-expanded="(faqOpen === 13).toString()" aria-controls="faq-panel-13" id="faq-button-13">
          <span class="text-lg font-semibold text-gray-900">What are journey pipelines?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 13 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-13" x-show="faqOpen === 13" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-13">
          <p class="text-base text-gray-600 leading-relaxed">Journey pipelines are kanban boards for your WhatsApp contacts. Create pipelines from templates (sales, support, e-commerce, events, and more), drag contacts between stages, and attach WhatsApp campaigns that fire when someone enters a stage. New contacts can auto-enroll, agents manage journeys from the inbox sidebar, and owners configure the app from the Journeys tab in Company Apps.</p>
        </div>
      </div>
      <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <button type="button" class="w-full flex items-center justify-between px-6 py-4 text-left" @click="faqOpen = faqOpen === 14 ? null : 14" :aria-expanded="(faqOpen === 14).toString()" aria-controls="faq-panel-14" id="faq-button-14">
          <span class="text-lg font-semibold text-gray-900">How do collections work — do you hold the money?</span>
          <svg class="w-4 h-4 text-gray-500 flex-shrink-0 transition-transform" :class="faqOpen === 14 ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <div id="faq-panel-14" x-show="faqOpen === 14" x-cloak class="px-6 pb-4" role="region" aria-labelledby="faq-button-14">
          <p class="text-base text-gray-600 leading-relaxed">No. Collections is the engine around your existing invoices: M-Pesa STK retry when a PIN times out, Paystack fallback, WhatsApp chase, and a board of open invoices. Settlement stays on your Daraja and Paystack keys. ConvoConnect is not a wallet, float, or PSP. Stripe is only for ConvoConnect SaaS billing.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FINAL CTA ===== -->
<section class="py-24 relative overflow-hidden" style="background:#eef8f3;">
  <div class="absolute inset-0" style="background:radial-gradient(ellipse 80% 60% at 50% 50%, rgba(37,211,102,0.15) 0%, transparent 70%);"></div>
  <div class="absolute top-0 left-0 right-0 h-px" style="background:linear-gradient(90deg,transparent,rgba(37,211,102,0.3),transparent);"></div>
  <div class="relative z-10 max-w-5xl mx-auto px-3 sm:px-4 lg:px-6 text-center">
    <div class="badge inline-flex mb-6">Guided activation</div>
    <h2 class="font-display section-title font-800 leading-tight mb-6 text-gray-900" style="letter-spacing:-0.02em;">
      Start selling on<br/><span class="grad-text">WhatsApp today</span>
    </h2>
    <p class="body-lg text-gray-600 mb-10 max-w-xl mx-auto">Recover carts, convert bookings, and collect unpaid invoices — journeys, catalogs, collections, campaigns, and inbox in one conversational commerce platform.</p>
    <div class="flex flex-wrap justify-center gap-4 mb-6">
      @if($registrationEnabled)
      <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-8 py-4 text-base font-semibold text-black rounded-xl transition-all hover:opacity-90 hover:scale-105" style="background:#25D366;">
        Start Free Today
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
      </a>
      @else
      <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-8 py-4 text-base font-semibold text-black rounded-xl transition-all hover:opacity-90 hover:scale-105" style="background:#25D366;">
        Sign In
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
      </a>
      @endif
      <a href="#pricing" class="inline-flex items-center gap-2 px-8 py-4 text-base font-semibold text-gray-800 rounded-xl border border-gray-200 hover:border-gray-300 transition-all hover:bg-white">See Pricing</a>
    </div>
    <p class="text-sm text-gray-600">Cancel anytime · Seat-based plans · Messaging &amp; managed AI credits</p>
  </div>
</section>
</main>

<!-- ===== FOOTER ===== -->
<footer class="border-t border-gray-200 py-16" style="background:#ecf6f1;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">
      <!-- Brand -->
      <div class="lg:col-span-2">
        <a href="{{ route('landing') }}" class="flex items-center gap-2.5 mb-4">
          <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#25D366,#075E54);">
          <img src="{{ config('settings.logo', asset('favicon.ico')) }}" alt="{{ config('settings.site_name', config('app.name')) }}" class="w-full h-full object-contain p-1" />
          </div>
          <span class="font-display font-800 text-lg tracking-tight text-gray-900">{{ config('settings.site_name', config('app.name')) }}</span>
        </a>
        <p class="text-base text-gray-600 leading-relaxed max-w-xs mb-5">The conversational commerce platform for WhatsApp — recover carts, convert bookings, collect unpaid invoices, and get paid on your M-Pesa or Paystack keys. Built on the official Meta WhatsApp Business API.</p>
      </div>
      <!-- Product -->
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4">Product</p>
        <ul class="space-y-2.5">
          <li><a href="#features" class="text-[15px] text-gray-600 hover:text-gray-900 transition-colors">Features</a></li>
          <li><a href="#journeys" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Journeys</a></li>
          <li><a href="#bookings" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Bookings</a></li>
          <li><a href="#catalog" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Product Catalog</a></li>
          <li><a href="#collections" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Collections</a></li>
          <li><a href="#automation" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Automation</a></li>
          <li><a href="#integrations" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Integrations</a></li>
          <li><a href="#api" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">API Docs</a></li>
          <li><a href="#pricing" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Pricing</a></li>
        </ul>
      </div>
      <!-- Company -->
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4">Company</p>
        <ul class="space-y-2.5">
          @if(isset($hasBlog) && $hasBlog)
          <li><a href="/blog" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Blog</a></li>
          @endif
          <li><a href="{{ route('policy.show') }}" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Privacy Policy</a></li>
          <li><a href="{{ route('terms.show') }}" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Terms of Service</a></li>
          <li><a href="#faq" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">FAQ</a></li>
          <li><a href="#pricing" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Pricing</a></li>
        </ul>
      </div>
    </div>

    <!-- Bottom bar -->
    <div class="border-t border-gray-200 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
      <p class="text-xs text-gray-500">© {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
      <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200" style="background:rgba(37,211,102,0.04);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.105.547 4.085 1.505 5.805L0 24l6.388-1.493A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.815 9.815 0 01-5.003-1.368l-.359-.214-3.72.869.936-3.624-.236-.373A9.818 9.818 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/></svg>
        <p class="text-xs text-gray-500">Built on <span class="text-gray-800">Meta WhatsApp Business API</span></p>
      </div>
    </div>
  </div>
</footer>
</body>
</html>
