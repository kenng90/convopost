<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
@php
  $siteName = config('settings.site_name', config('app.name'));
  $practiceName = $siteName.' Automation';
  $metaTitle = $practiceName.' — AI workers that run the work';
  $metaDescription = 'We audit processes, automate the handoffs that pay, and stand up AI workers that answer from your data, take calls, and trigger the next step. Cloud by default; on your network when required. Separate from '.$siteName.' WhatsApp SaaS.';
  $supportEmail = config('settings.contact_email', 'support@convoconnect.tech');
@endphp
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
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet" />
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        ink: { DEFAULT: '#0a1628', soft: '#122438' },
        signal: { DEFAULT: '#0d9488', light: '#2dd4bf', soft: '#ccfbf1' },
        ember: { DEFAULT: '#fb923c', deep: '#ea580c' },
      },
      fontFamily: {
        display: ['Sora', 'sans-serif'],
        body: ['Figtree', 'sans-serif'],
      }
    }
  }
}
</script>
<style>
  *, body { font-family: 'Figtree', sans-serif; font-size: 17px; line-height: 1.65; }
  h1,h2,h3,h4,h5,.font-display { font-family: 'Sora', sans-serif; }
  [x-cloak] { display: none !important; }

  :root {
    --signal: #0d9488;
    --ember: #fb923c;
    --ink: #0a1628;
    --paper: #f4f7f9;
  }

  html { scroll-padding-top: 72px; }

  .noise::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 0;
  }

  .hero-mesh {
    background:
      radial-gradient(ellipse 70% 50% at 15% 20%, rgba(45,212,191,0.22) 0%, transparent 55%),
      radial-gradient(ellipse 50% 40% at 85% 30%, rgba(251,146,60,0.16) 0%, transparent 50%),
      radial-gradient(ellipse 60% 50% at 50% 100%, rgba(13,148,136,0.12) 0%, transparent 55%),
      #0a1628;
  }

  .grid-fine {
    background-image:
      linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
    background-size: 48px 48px;
  }

  .navbar-blur {
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    background: rgba(244,247,249,0.88);
    border-bottom: 1px solid rgba(10,22,40,0.06);
  }

  .section-label {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--signal);
  }

  .section-title {
    font-size: clamp(2rem, 4vw, 3.25rem);
    letter-spacing: -0.03em;
    line-height: 1.1;
  }

  .outcome-num {
    font-size: clamp(2.75rem, 5vw, 4rem);
    letter-spacing: -0.04em;
    line-height: 1;
  }

  .grad-text { color: var(--signal); }

  @keyframes rise {
    from { opacity: 0; transform: translateY(18px); }
    to { opacity: 1; transform: translateY(0); }
  }
  @keyframes marquee {
    from { transform: translateX(0); }
    to { transform: translateX(-50%); }
  }

  .rise { animation: rise 0.7s ease both; }
  .rise-delay-1 { animation-delay: 0.1s; }
  .rise-delay-2 { animation-delay: 0.2s; }
  .rise-delay-3 { animation-delay: 0.35s; }

  .marquee-track {
    display: flex;
    width: max-content;
    animation: marquee 32s linear infinite;
  }

  .infographic-panel svg path[stroke-dasharray] {
    animation: none;
  }

  @media (prefers-reduced-motion: reduce) {
    .rise, .marquee-track { animation: none !important; }
  }

  .stat-item + .stat-item {
    border-left: 1px solid rgba(10,22,40,0.08);
  }
</style>
</head>

<body class="bg-[var(--paper)] text-slate-900" x-data="{ mobileOpen: false, workTab: 'audit' }">

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-signal focus:text-white focus:font-semibold">Skip to content</a>

<!-- NAV -->
<nav class="navbar-blur fixed top-0 left-0 right-0 z-50" style="height:72px;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6 flex items-center justify-between h-full">
    <a href="{{ route('services.automation') }}" class="flex items-center gap-2.5 flex-shrink-0">
      <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#0d9488,#0a1628);">
        <img src="{{ config('settings.logo', asset('favicon.ico')) }}" alt="{{ $practiceName }}" class="w-full h-full object-contain p-1" />
      </div>
      <span class="font-display font-800 text-lg tracking-tight text-ink">{{ $practiceName }}</span>
    </a>

    <div class="hidden lg:flex items-center gap-7">
      <a href="#what-we-do" class="text-sm text-slate-600 hover:text-ink transition-colors">What we do</a>
      <a href="#how-it-works" class="text-sm text-slate-600 hover:text-ink transition-colors">How it works</a>
      <a href="#catalog" class="text-sm text-slate-600 hover:text-ink transition-colors">Catalog</a>
      <a href="#packages" class="text-sm text-slate-600 hover:text-ink transition-colors">Packages</a>
      <a href="{{ route('landing') }}" class="text-sm text-slate-600 hover:text-ink transition-colors">WhatsApp SaaS</a>
    </div>

    <div class="hidden lg:flex items-center gap-3">
      <a href="mailto:{{ $supportEmail }}?subject=Automation%20consultation" class="text-base font-semibold px-5 py-2.5 rounded-lg text-white transition-all hover:opacity-90" style="background:#0d9488;">Book a consult</a>
    </div>

    <button type="button" class="lg:hidden text-slate-600" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen.toString()" aria-controls="mobile-menu" aria-label="Toggle navigation">
      <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>

  <div id="mobile-menu" x-show="mobileOpen" x-cloak class="lg:hidden border-t border-slate-200 px-4 py-4 space-y-1 bg-white/95">
    <a href="#what-we-do" class="block py-2.5 text-base text-slate-700" @click="mobileOpen=false">What we do</a>
    <a href="#how-it-works" class="block py-2.5 text-base text-slate-700" @click="mobileOpen=false">How it works</a>
    <a href="#catalog" class="block py-2.5 text-base text-slate-700" @click="mobileOpen=false">Catalog</a>
    <a href="#packages" class="block py-2.5 text-base text-slate-700" @click="mobileOpen=false">Packages</a>
    <a href="{{ route('landing') }}" class="block py-2.5 text-base text-slate-700" @click="mobileOpen=false">WhatsApp SaaS</a>
    <a href="mailto:{{ $supportEmail }}?subject=Automation%20consultation" class="block mt-3 text-center py-2.5 text-sm font-semibold text-white rounded-lg" style="background:#0d9488;">Book a consult</a>
  </div>
</nav>

<main id="main-content">

<!-- HERO -->
<section class="relative min-h-[100svh] flex items-center pt-28 pb-16 overflow-hidden noise hero-mesh">
  <div class="absolute inset-0 grid-fine opacity-60"></div>
  <div class="relative z-10 max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6 w-full">
    <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
      <div>
        <p class="rise text-[12px] font-semibold uppercase tracking-[0.2em] mb-5" style="color:#2dd4bf;">Process automation practice</p>
        <h1 class="rise rise-delay-1 font-display text-[clamp(2.6rem,5.5vw,4.4rem)] font-800 text-white leading-[1.05] tracking-tight mb-6">
          Automation that<br/>
          <span style="color:#2dd4bf;">runs the work.</span>
        </h1>
        <p class="rise rise-delay-2 text-lg text-slate-300 max-w-xl mb-8 leading-relaxed">
          Growing businesses drown in handoffs between chat, the phone, CRM, and spreadsheets. We find the waste, stand up AI workers on your data, and keep them running — so your team ships more and chases less.
        </p>
        <div class="rise rise-delay-3 flex flex-wrap gap-3">
          <a href="mailto:{{ $supportEmail }}?subject=Automation%20consultation" class="inline-flex items-center gap-2 px-7 py-3.5 text-base font-semibold text-ink rounded-xl transition-transform hover:scale-[1.02]" style="background:#2dd4bf;">
            Book a consult
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
          </a>
          <a href="#how-it-works" class="inline-flex items-center gap-2 px-7 py-3.5 text-base font-semibold text-white rounded-xl border border-white/20 hover:bg-white/5 transition-colors">
            See how it works
          </a>
        </div>
        <p class="mt-6 text-sm text-slate-400">Separate from {{ $siteName }} WhatsApp SaaS — workers, workflows, and on-network when you need it.</p>
      </div>
      <div class="rise rise-delay-2">
        @include('wpsupportlanding::landing.partials.infographics.services.hero_system')
      </div>
    </div>
  </div>
</section>

<!-- TRUST / TOOLS STRIP -->
<section class="border-y border-slate-200 bg-white py-8 overflow-hidden">
  <p class="text-center text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 mb-5">Workers that sit on the channels and data you already have</p>
  <div class="relative">
    <div class="marquee-track gap-10 px-6 text-slate-500 font-display font-700 text-sm sm:text-base">
      @foreach(['WhatsApp','Voice calls','Your knowledge base','Human handoff','CRM','Payments','Bookings','Tickets','Cloud LLM','On-network option','Ollama','n8n'] as $tool)
        <span class="whitespace-nowrap">{{ $tool }}</span>
        <span class="text-signal/40">·</span>
      @endforeach
      @foreach(['WhatsApp','Voice calls','Your knowledge base','Human handoff','CRM','Payments','Bookings','Tickets','Cloud LLM','On-network option','Ollama','n8n'] as $tool)
        <span class="whitespace-nowrap" aria-hidden="true">{{ $tool }}</span>
        <span class="text-signal/40" aria-hidden="true">·</span>
      @endforeach
    </div>
  </div>
</section>

<!-- WHAT WE DO (Nominal-style tabs) -->
<section id="what-we-do" class="py-24 bg-white">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-12 max-w-2xl">
      <p class="section-label mb-3">What we do</p>
      <h2 class="font-display section-title font-800 mb-4">Introducing end-to-end<br/><span class="grad-text">process automation.</span></h2>
      <p class="text-slate-600">Traditional freelancers install a few Zaps. We audit the business, automate the handoffs that pay, stand up workers that answer and act, and keep inference on your network when you need it.</p>
    </div>

    <div class="flex flex-wrap gap-2 mb-8">
      @foreach([
        'audit' => 'Audit',
        'automate' => 'Automate',
        'workers' => 'AI workers',
        'network' => 'On-network',
      ] as $key => $label)
        <button type="button"
          @click="workTab = '{{ $key }}'"
          :class="workTab === '{{ $key }}' ? 'bg-ink text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
          class="px-4 py-2 rounded-full text-sm font-semibold transition-colors">
          {{ $label }}
        </button>
      @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-8 items-start">
      <div>
        <div x-show="workTab === 'audit'">
          <h3 class="font-display text-3xl font-800 text-ink mb-3">Business process audit</h3>
          <p class="text-slate-600 mb-6">Everything required to see where time and money leak — before you build anything.</p>
          <ul class="space-y-3 text-slate-700">
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Analyze current workflows</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Identify repetitive, manual tasks</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Find automation opportunities</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Calculate potential time and cost savings</li>
          </ul>
        </div>
        <div x-show="workTab === 'automate'" x-cloak>
          <h3 class="font-display text-3xl font-800 text-ink mb-3">Workflow automation</h3>
          <p class="text-slate-600 mb-6">Wire the 2–3 handoffs that pay for the engagement: approvals, reminders, documents, and CRM updates.</p>
          <ul class="space-y-3 text-slate-700">
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Customer &amp; employee onboarding</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Invoice &amp; expense approvals</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Leave requests &amp; task reminders</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Proposal, quote &amp; contract generation</li>
          </ul>
        </div>
        <div x-show="workTab === 'workers'" x-cloak>
          <h3 class="font-display text-3xl font-800 text-ink mb-3">AI workers that use your data</h3>
          <p class="text-slate-600 mb-6">They answer FAQs, take calls, draft replies, and trigger the next step — payment, booking, ticket, or a human.</p>
          <ul class="space-y-3 text-slate-700">
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Retrieve from your SOPs, catalogs, and chats — not a generic model</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Take calls and log a brief on the contact</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Trigger payments, bookings, and tickets</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Hand off to a person when asked or when unsure</li>
          </ul>
        </div>
        <div x-show="workTab === 'network'" x-cloak>
          <h3 class="font-display text-3xl font-800 text-ink mb-3">Stay on your network when it matters</h3>
          <p class="text-slate-600 mb-6">Cloud is the default. For clinics, schools, and anyone who cannot send customer data out, the same worker can run on a machine in your office.</p>
          <ul class="space-y-3 text-slate-700">
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Documents and chats stay inside your LAN</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Point the worker at a local model (Ollama / compatible)</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Same playbook, same handoff, private inference</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Enterprise option — not required for Starter</li>
          </ul>
        </div>
      </div>
      <div>
        <div x-show="workTab === 'audit'">
          @include('wpsupportlanding::landing.partials.infographics.services.audit_funnel')
        </div>
        <div x-show="workTab === 'automate'" x-cloak>
          @include('wpsupportlanding::landing.partials.infographics.services.workflow_engine')
        </div>
        <div x-show="workTab === 'workers'" x-cloak>
          @include('wpsupportlanding::landing.partials.infographics.services.ai_workers')
        </div>
        <div x-show="workTab === 'network'" x-cloak>
          @include('wpsupportlanding::landing.partials.infographics.services.on_network')
        </div>
      </div>
    </div>
  </div>
</section>

<!-- OUTCOMES -->
<section class="py-24 border-y border-slate-200" style="background:#ecf4f5;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="text-center mb-14">
      <p class="section-label mb-3">Outcomes</p>
      <h2 class="font-display section-title font-800">Autonomous where it should be.<br/><span class="grad-text">Human where it matters.</span></h2>
    </div>
    <div class="grid sm:grid-cols-3 gap-8 max-w-5xl mx-auto text-center">
      <div class="stat-item px-4">
        <p class="font-display outcome-num font-800 grad-text mb-2">10–40h</p>
        <p class="text-base text-slate-600">Typical weekly hours reclaimed after first automations</p>
      </div>
      <div class="stat-item px-4">
        <p class="font-display outcome-num font-800 grad-text mb-2">0</p>
        <p class="text-base text-slate-600">Rip-and-replace required — we work with your stack</p>
      </div>
      <div class="stat-item px-4">
        <p class="font-display outcome-num font-800 grad-text mb-2">90d</p>
        <p class="text-base text-slate-600">Support window on Growth engagements to stabilize flows</p>
      </div>
    </div>
    <div class="mt-14">
      @include('wpsupportlanding::landing.partials.infographics.services.savings_loop')
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section id="how-it-works" class="py-24 bg-white">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-14 max-w-2xl">
      <p class="section-label mb-3">How it works</p>
      <h2 class="font-display section-title font-800 mb-4">Four jobs.<br/><span class="grad-text">One engagement.</span></h2>
      <p class="text-slate-600">Audit, automate, stand up workers, and keep inference on-network when customer data cannot leave the building.</p>
    </div>
    @include('wpsupportlanding::landing.partials.infographics.services.practice_pillars')
  </div>
</section>

<!-- CATALOG -->
<section id="catalog" class="py-24" style="background:#ecf4f5;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    @include('wpsupportlanding::landing.partials.infographics.services.service_catalog')
  </div>
</section>

<!-- INTEGRATIONS -->
<section id="integrations" class="py-24 bg-white">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-10 max-w-2xl">
      <p class="section-label mb-3">Your stack</p>
      <h2 class="font-display section-title font-800 mb-4">The worker sits<br/><span class="grad-text">in the middle.</span></h2>
      <p class="text-slate-600">Chat, voice, CRM, payments, and the docs you already have. Cloud by default — on-network when required. No rip-and-replace.</p>
    </div>
    @include('wpsupportlanding::landing.partials.infographics.services.integrations_constellation')
  </div>
</section>

<!-- PACKAGES -->
<section id="packages" class="py-24" style="background:#ecf4f5;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    @include('wpsupportlanding::landing.partials.infographics.services.package_ladder')
  </div>
</section>

<!-- TRAINING -->
<section class="py-20 bg-white border-y border-slate-200">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6 grid lg:grid-cols-2 gap-10 items-center">
    <div>
      <p class="section-label mb-3">Training &amp; support</p>
      <h2 class="font-display text-3xl sm:text-4xl font-800 text-ink mb-4">Workers come with a playbook, not a black box.</h2>
      <p class="text-slate-600 mb-6">Every worker has limits: what it can answer, what it can do, and when it must hand off. Staff training, documentation, and monthly reviews keep that honest as the business changes.</p>
      <ul class="grid sm:grid-cols-2 gap-3 text-sm text-slate-700">
        <li class="flex gap-2"><span class="text-signal">✓</span> Staff training sessions</li>
        <li class="flex gap-2"><span class="text-signal">✓</span> Living documentation</li>
        <li class="flex gap-2"><span class="text-signal">✓</span> Maintenance &amp; monitoring</li>
        <li class="flex gap-2"><span class="text-signal">✓</span> Monthly optimization reviews</li>
      </ul>
    </div>
    <div class="rounded-3xl border border-slate-200 p-8" style="background:linear-gradient(160deg,#0a1628,#122438);">
      <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-4" style="color:#2dd4bf;">Also available</p>
      <h3 class="font-display text-2xl font-800 text-white mb-3">Need WhatsApp commerce?</h3>
      <p class="text-slate-300 mb-6">{{ $siteName }} SaaS runs selling, journeys, bookings, and payments on WhatsApp. This practice covers the workers and workflows around — and beyond — chat.</p>
      <a href="{{ route('landing') }}" class="inline-flex items-center gap-2 text-sm font-semibold" style="color:#2dd4bf;">
        Explore WhatsApp SaaS
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
      </a>
    </div>
  </div>
</section>

<!-- FINAL CTA -->
<section id="book" class="py-28 relative overflow-hidden" style="background:#0a1628;">
  <div class="absolute inset-0" style="background:radial-gradient(ellipse 70% 60% at 50% 40%, rgba(45,212,191,0.18) 0%, transparent 65%);"></div>
  <div class="relative z-10 max-w-4xl mx-auto px-3 sm:px-4 lg:px-6 text-center">
    <p class="text-[12px] font-semibold uppercase tracking-[0.2em] mb-5" style="color:#2dd4bf;">Ready when you are</p>
    <h2 class="font-display section-title font-800 text-white mb-6">Move from doing the work<br/>to running the business.</h2>
    <p class="text-lg text-slate-300 mb-10 max-w-xl mx-auto">Tell us where hours disappear. We’ll map the automations, decide where a worker earns its keep, and propose a Starter, Growth, or Enterprise path.</p>
    <a href="mailto:{{ $supportEmail }}?subject=Automation%20consultation" class="inline-flex items-center gap-2 px-8 py-4 text-base font-semibold text-ink rounded-xl hover:scale-[1.02] transition-transform" style="background:#2dd4bf;">
      Book a consult
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
    </a>
  </div>
</section>

</main>

<footer class="border-t border-slate-200 py-14 bg-white">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
    <div>
      <p class="font-display font-800 text-ink mb-1">{{ $practiceName }}</p>
      <p class="text-sm text-slate-600 max-w-md">Process automation for growing businesses — audit, automate, stand up AI workers, keep data on-network when required.</p>
    </div>
    <div class="flex flex-wrap gap-4 text-sm text-slate-600">
      <a href="{{ route('landing') }}" class="hover:text-ink">WhatsApp SaaS</a>
      <a href="{{ route('policy.show') }}" class="hover:text-ink">Privacy</a>
      <a href="{{ route('terms.show') }}" class="hover:text-ink">Terms</a>
      <a href="mailto:{{ $supportEmail }}" class="hover:text-ink">{{ $supportEmail }}</a>
    </div>
  </div>
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6 mt-8 pt-6 border-t border-slate-100">
    <p class="text-xs text-slate-500">© {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
  </div>
</footer>

</body>
</html>
