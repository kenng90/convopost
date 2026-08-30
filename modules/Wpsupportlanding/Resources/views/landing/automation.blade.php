<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
@php
  $siteName = \Modules\Wpsupportlanding\Support\ChatDukaBrand::name();
  $practiceName = $siteName.' Automation';
  $metaTitle = $practiceName.' — Custom AI operating systems for real workflows';
  $metaDescription = 'We map how your business already works, connect the tools you use, and deploy an AI layer that prepares tasks, follow-ups, and decisions for your team to approve. Deploy first, then productize what repeats. Separate from '.$siteName.' WhatsApp SaaS.';
  $supportEmail = \Modules\Wpsupportlanding\Support\ChatDukaBrand::supportEmail();
  $bookHref = 'mailto:'.$supportEmail.'?subject='.rawurlencode('Deployment call');
@endphp
@include('wpsupportlanding::landing.partials.chatduka.favicon')
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

<body class="bg-[var(--paper)] text-slate-900" x-data="{ mobileOpen: false, deployTab: 'hospitality' }">

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-signal focus:text-white focus:font-semibold">Skip to content</a>

<nav class="navbar-blur fixed top-0 left-0 right-0 z-50" style="height:72px;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6 flex items-center justify-between h-full">
    <a href="{{ route('services.automation') }}" class="flex items-center gap-2.5 flex-shrink-0">
      <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#0d9488,#0a1628);">
        <img src="{{ \Modules\Wpsupportlanding\Support\ChatDukaBrand::markUrl() }}" alt="{{ $practiceName }}" class="w-full h-full object-contain p-1" />
      </div>
      <span class="font-display font-800 text-lg tracking-tight text-ink">{{ $practiceName }}</span>
    </a>

    <div class="hidden lg:flex items-center gap-7">
      <a href="#what-we-do" class="text-sm text-slate-600 hover:text-ink transition-colors">The gap</a>
      <a href="#how-it-works" class="text-sm text-slate-600 hover:text-ink transition-colors">How it works</a>
      <a href="#deploy" class="text-sm text-slate-600 hover:text-ink transition-colors">Where we deploy</a>
      <a href="#catalog" class="text-sm text-slate-600 hover:text-ink transition-colors">Capabilities</a>
      <a href="{{ route('landing') }}" class="text-sm text-slate-600 hover:text-ink transition-colors">WhatsApp SaaS</a>
    </div>

    <div class="hidden lg:flex items-center gap-3">
      <a href="{{ $bookHref }}" class="text-base font-semibold px-5 py-2.5 rounded-lg text-white transition-all hover:opacity-90" style="background:#0d9488;">Book a deployment</a>
    </div>

    <button type="button" class="lg:hidden text-slate-600" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen.toString()" aria-controls="mobile-menu" aria-label="Toggle navigation">
      <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-join="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-join="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>

  <div id="mobile-menu" x-show="mobileOpen" x-cloak class="lg:hidden border-t border-slate-200 px-4 py-4 space-y-1 bg-white/95">
    <a href="#what-we-do" class="block py-2.5 text-base text-slate-700" @click="mobileOpen=false">The gap</a>
    <a href="#how-it-works" class="block py-2.5 text-base text-slate-700" @click="mobileOpen=false">How it works</a>
    <a href="#deploy" class="block py-2.5 text-base text-slate-700" @click="mobileOpen=false">Where we deploy</a>
    <a href="#catalog" class="block py-2.5 text-base text-slate-700" @click="mobileOpen=false">Capabilities</a>
    <a href="{{ route('landing') }}" class="block py-2.5 text-base text-slate-700" @click="mobileOpen=false">WhatsApp SaaS</a>
    <a href="{{ $bookHref }}" class="block mt-3 text-center py-2.5 text-sm font-semibold text-white rounded-lg" style="background:#0d9488;">Book a deployment</a>
  </div>
</nav>

<main id="main-content">

<section class="relative min-h-[100svh] flex items-center pt-28 pb-16 overflow-hidden noise hero-mesh">
  <div class="absolute inset-0 grid-fine opacity-60"></div>
  <div class="relative z-10 max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6 w-full">
    <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
      <div>
        <p class="rise text-[12px] font-semibold uppercase tracking-[0.2em] mb-5" style="color:#2dd4bf;">Custom AI operating systems</p>
        <h1 class="rise rise-delay-1 font-display text-[clamp(2.6rem,5.5vw,4.4rem)] font-800 text-white leading-[1.05] tracking-tight mb-6">
          Built around<br/>
          <span style="color:#2dd4bf;">your real workflows.</span>
        </h1>
        <p class="rise rise-delay-2 text-lg text-slate-300 max-w-xl mb-8 leading-relaxed">
          We connect to the tools your company already uses and turn scattered emails, documents, chats, approvals, and tasks into structured action — prepared for your team to review.
        </p>
        <div class="rise rise-delay-3 flex flex-wrap gap-3">
          <a href="{{ $bookHref }}" class="inline-flex items-center gap-2 px-7 py-3.5 text-base font-semibold text-ink rounded-xl transition-transform hover:scale-[1.02]" style="background:#2dd4bf;">
            Book a deployment
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-join="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
          </a>
          <a href="#how-it-works" class="inline-flex items-center gap-2 px-7 py-3.5 text-base font-semibold text-white rounded-xl border border-white/20 hover:bg-white/5 transition-colors">
            See how it works
          </a>
        </div>
        <p class="mt-6 text-sm text-slate-400">Separate from {{ $siteName }} WhatsApp SaaS — the AI layer around the rest of the operation.</p>
      </div>
      <div class="rise rise-delay-2">
        @include('wpsupportlanding::landing.partials.infographics.services.hero_system')
      </div>
    </div>
  </div>
</section>

<section class="border-y border-slate-200 bg-white py-8 overflow-hidden">
  <p class="text-center text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 mb-5">Works inside the stack you already run</p>
  <div class="relative">
    <div class="marquee-track gap-10 px-6 text-slate-500 font-display font-700 text-sm sm:text-base">
      @foreach(['Email','Documents','Spreadsheets','WhatsApp','Voice','CRM','Approvals','Bookings','Payments','Accounting','Drive','Human inbox'] as $tool)
        <span class="whitespace-nowrap">{{ $tool }}</span>
        <span class="text-signal/40">·</span>
      @endforeach
      @foreach(['Email','Documents','Spreadsheets','WhatsApp','Voice','CRM','Approvals','Bookings','Payments','Accounting','Drive','Human inbox'] as $tool)
        <span class="whitespace-nowrap" aria-hidden="true">{{ $tool }}</span>
        <span class="text-signal/40" aria-hidden="true">·</span>
      @endforeach
    </div>
  </div>
</section>

<section id="what-we-do" class="py-24 bg-white">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-12 max-w-2xl">
      <p class="section-label mb-3">The gap</p>
      <h2 class="font-display section-title font-800 mb-4">AI tools are powerful.<br/><span class="grad-text">Most businesses are not built to use them.</span></h2>
      <p class="text-slate-600">Companies run on emails, files, spreadsheets, chats, meetings, approvals, and people remembering what happens next. Generic AI does not understand that workflow — or the context behind it.</p>
    </div>

    <div class="grid md:grid-cols-3 gap-4">
      @foreach([
        ['01', 'Context is scattered', 'Information lives in email threads, shared drives, spreadsheets, and chat logs. No tool has the full picture, so nothing can act on it.'],
        ['02', 'Work is manual', 'Teams spend hours moving data between tools, writing the same follow-ups, and preparing status updates that should not need a person.'],
        ['03', 'AI is disconnected', 'Generic tools cannot read your data, understand your workflow, or take action inside the systems where work actually happens.'],
      ] as [$num, $title, $body])
        <div class="rounded-3xl border border-slate-200 bg-[var(--paper)] p-7">
          <p class="font-display text-3xl font-800 mb-4" style="color:rgba(13,148,136,0.35);">{{ $num }}</p>
          <h3 class="font-display text-xl font-800 text-ink mb-3">{{ $title }}</h3>
          <p class="text-slate-600">{{ $body }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>

<section id="how-it-works" class="py-24" style="background:#ecf4f5;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-10 max-w-2xl">
      <p class="section-label mb-3">How it works</p>
      <h2 class="font-display section-title font-800 mb-4">The AI layer around<br/><span class="grad-text">your actual operation.</span></h2>
      <p class="text-slate-600">We map how the business already works, connect the systems already in use, and deploy workflows that prepare tasks, reports, approvals, follow-ups, and decisions for your team to review.</p>
    </div>
    @include('wpsupportlanding::landing.partials.infographics.services.ai_workers')
  </div>
</section>

<section id="deploy" class="py-24 bg-white">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-10 max-w-2xl">
      <p class="section-label mb-3">Where we deploy</p>
      <h2 class="font-display section-title font-800 mb-4">Service businesses that<br/><span class="grad-text">run on coordination.</span></h2>
      <p class="text-slate-600">We start where hours disappear — then expand. Not a chatbot install. A first workflow inside the real operation.</p>
    </div>

    <div class="flex flex-wrap gap-2 mb-8">
      @foreach([
        'hospitality' => 'Hospitality &amp; clinics',
        'commerce' => 'Retail &amp; commerce',
        'services' => 'Professional services',
      ] as $key => $label)
        <button type="button"
          @click="deployTab = '{{ $key }}'"
          :class="deployTab === '{{ $key }}' ? 'bg-ink text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
          class="px-4 py-2 rounded-full text-sm font-semibold transition-colors">
          {!! $label !!}
        </button>
      @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-10 items-start">
      <div>
        <div x-show="deployTab === 'hospitality'">
          <h3 class="font-display text-3xl font-800 text-ink mb-3">Bookings, intake, and the front desk</h3>
          <p class="text-slate-600 mb-6">Hotels, clinics, and appointment-led teams drowning in WhatsApp, calls, and “did we confirm that?”</p>
          <ul class="space-y-3 text-slate-700">
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Intake and qualification from chat or a call</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Booking confirmations and no-show follow-ups</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Call briefs logged on the contact</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Daily “what needs a person” inbox</li>
          </ul>
        </div>
        <div x-show="deployTab === 'commerce'" x-cloak>
          <h3 class="font-display text-3xl font-800 text-ink mb-3">Orders, catalogs, and payment chase</h3>
          <p class="text-slate-600 mb-6">Retail and commerce teams moving stock, invoices, and customer questions across chat, sheets, and the till.</p>
          <ul class="space-y-3 text-slate-700">
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Order and payment status from the conversation</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Overdue follow-ups drafted for approval</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Catalog and SOP answers with sources</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Exceptions routed to a human before they send</li>
          </ul>
        </div>
        <div x-show="deployTab === 'services'" x-cloak>
          <h3 class="font-display text-3xl font-800 text-ink mb-3">Documents, invoices, and client ops</h3>
          <p class="text-slate-600 mb-6">Firms and agencies running on email, files, proposals, and people chasing status across a dozen tools.</p>
          <ul class="space-y-3 text-slate-700">
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Invoice classification and routing</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Proposal and document preparation</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Client follow-ups with evidence attached</li>
            <li class="flex gap-2"><span class="text-signal font-bold">·</span> Week-end status reports from the work already done</li>
          </ul>
        </div>
      </div>
      <div>
        @include('wpsupportlanding::landing.partials.infographics.services.audit_funnel')
      </div>
    </div>
  </div>
</section>

<section id="catalog" class="py-24" style="background:#ecf4f5;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    @include('wpsupportlanding::landing.partials.infographics.services.service_catalog')
  </div>
</section>

<section id="integrations" class="py-24 bg-white">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-10 max-w-2xl">
      <p class="section-label mb-3">Your stack</p>
      <h2 class="font-display section-title font-800 mb-4">No rip-and-replace.<br/><span class="grad-text">AI inside the current stack.</span></h2>
      <p class="text-slate-600">Email, documents, spreadsheets, CRM, chat, voice, and payments. The operating system works where the work already lives.</p>
    </div>
    @include('wpsupportlanding::landing.partials.infographics.services.integrations_constellation')
  </div>
</section>

<section id="process" class="py-24" style="background:#ecf4f5;">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-12 max-w-2xl">
      <p class="section-label mb-3">Deployment</p>
      <h2 class="font-display section-title font-800 mb-4">From one workflow<br/><span class="grad-text">to an operating system.</span></h2>
      <p class="text-slate-600">We deploy first. Then we productize what repeats — so the work compounds instead of rotting as a one-off.</p>
    </div>
    @include('wpsupportlanding::landing.partials.infographics.services.practice_pillars')
    <div class="mt-12">
      @include('wpsupportlanding::landing.partials.infographics.services.package_ladder')
    </div>
  </div>
</section>

<section class="py-20 bg-white border-y border-slate-200">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6">
    <div class="mb-10 max-w-2xl">
      <p class="section-label mb-3">Built for operators</p>
      <h2 class="font-display text-3xl sm:text-4xl font-800 text-ink mb-4">Controlled AI for work that matters.</h2>
      <p class="text-slate-600">AI suggests, prepares, and routes. Your team stays in control — with evidence, an audit trail, and data boundaries.</p>
    </div>
    <div class="grid lg:grid-cols-2 gap-10 items-start">
      <ul class="grid sm:grid-cols-2 gap-3 text-sm text-slate-700">
        <li class="flex gap-2"><span class="text-signal">✓</span> Source-backed outputs with evidence</li>
        <li class="flex gap-2"><span class="text-signal">✓</span> Human approval before any action executes</li>
        <li class="flex gap-2"><span class="text-signal">✓</span> Workspace-level data boundaries</li>
        <li class="flex gap-2"><span class="text-signal">✓</span> Audit trails for every AI decision</li>
        <li class="flex gap-2"><span class="text-signal">✓</span> Role-based views and access</li>
        <li class="flex gap-2"><span class="text-signal">✓</span> On-network inference when required</li>
      </ul>
      <div class="rounded-3xl border border-slate-200 p-8" style="background:linear-gradient(160deg,#0a1628,#122438);">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-4" style="color:#2dd4bf;">Also available</p>
        <h3 class="font-display text-2xl font-800 text-white mb-3">Need WhatsApp commerce?</h3>
        <p class="text-slate-300 mb-6">{{ $siteName }} SaaS is the self-serve inbox for selling and support on WhatsApp, Instagram, and Messenger. This practice is the AI operating system around — and beyond — chat.</p>
        <a href="{{ route('landing') }}" class="inline-flex items-center gap-2 text-sm font-semibold" style="color:#2dd4bf;">
          Explore WhatsApp SaaS
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-join="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
      </div>
    </div>
    <div class="mt-12">
      @include('wpsupportlanding::landing.partials.infographics.services.savings_loop')
    </div>
    <div class="mt-8">
      @include('wpsupportlanding::landing.partials.infographics.services.on_network')
    </div>
  </div>
</section>

<section id="book" class="py-28 relative overflow-hidden" style="background:#0a1628;">
  <div class="absolute inset-0" style="background:radial-gradient(ellipse 70% 60% at 50% 40%, rgba(45,212,191,0.18) 0%, transparent 65%);"></div>
  <div class="relative z-10 max-w-4xl mx-auto px-3 sm:px-4 lg:px-6 text-center">
    <p class="text-[12px] font-semibold uppercase tracking-[0.2em] mb-5" style="color:#2dd4bf;">Show us the workflow</p>
    <h2 class="font-display section-title font-800 text-white mb-6">We’ll show you what AI can do<br/>inside it.</h2>
    <p class="text-lg text-slate-300 mb-10 max-w-xl mx-auto">Tell us where hours disappear. We map the first workflow, put approval gates in place, and deploy — then expand what repeats into the operating system.</p>
    <a href="{{ $bookHref }}" class="inline-flex items-center gap-2 px-8 py-4 text-base font-semibold text-ink rounded-xl hover:scale-[1.02] transition-transform" style="background:#2dd4bf;">
      Book a deployment
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-join="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
    </a>
  </div>
</section>

</main>

<footer class="border-t border-slate-200 py-14 bg-white">
  <div class="max-w-[90rem] mx-auto px-3 sm:px-4 lg:px-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
    <div>
      <p class="font-display font-800 text-ink mb-1">{{ $practiceName }}</p>
      <p class="text-sm text-slate-600 max-w-md">Custom AI operating systems for real business workflows — deploy first, then productize what repeats.</p>
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
