{{-- Commerce journey loop — Infobip-style horizontal flow --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-gray-200 p-6 sm:p-8" style="background:linear-gradient(180deg,#f3faf6 0%,#ffffff 100%);">
  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-6">
    <div>
      <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#25D366;">Commerce loop</p>
      <h3 class="font-display text-2xl sm:text-3xl font-800 text-gray-900">From first message to paid order</h3>
    </div>
    <p class="text-base text-gray-600 max-w-xs">A continuous loop — not a funnel that drops off after checkout.</p>
  </div>

  <svg viewBox="0 0 960 220" class="w-full h-auto hidden md:block" role="img" aria-label="Commerce journey from discovery through payment and retention">
    <defs>
      <marker id="arrowGreen" markerWidth="8" markerHeight="8" refX="6" refY="4" orient="auto">
        <path d="M0,0 L8,4 L0,8 Z" fill="#25D366"/>
      </marker>
      <linearGradient id="flowGrad" x1="0%" y1="0%" x2="100%" y2="0%">
        <stop offset="0%" stop-color="#25D366" stop-opacity="0.2"/>
        <stop offset="100%" stop-color="#25D366" stop-opacity="0.8"/>
      </linearGradient>
    </defs>

    {{-- Path --}}
    <path d="M70 110 H890" stroke="url(#flowGrad)" stroke-width="2" stroke-dasharray="6 6" fill="none" marker-end="url(#arrowGreen)"/>

    @php
      $steps = [
        [90, '01', 'Discover', 'Campaign or widget'],
        [250, '02', 'Engage', 'Inbox + Copilot'],
        [410, '03', 'Qualify', 'Journey stage'],
        [570, '04', 'Convert', 'Catalog / booking'],
        [730, '05', 'Collect', 'M-Pesa / Paystack'],
        [890, '06', 'Retain', 'Reminder + rebuy'],
      ];
    @endphp
    @foreach($steps as [$cx, $num, $title, $sub])
      <g>
        <circle cx="{{ $cx }}" cy="110" r="28" fill="#ffffff" stroke="#25D366" stroke-width="2"/>
        <text x="{{ $cx }}" y="114" text-anchor="middle" fill="#25D366" font-size="12" font-weight="700" font-family="Syne,sans-serif">{{ $num }}</text>
        <text x="{{ $cx }}" y="160" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Syne,sans-serif">{{ $title }}</text>
        <text x="{{ $cx }}" y="178" text-anchor="middle" fill="#64748b" font-size="10" font-family="DM Sans,sans-serif">{{ $sub }}</text>
      </g>
    @endforeach
  </svg>

  {{-- Mobile stacked --}}
  <div class="md:hidden space-y-3">
    @foreach([
      ['01', 'Discover', 'Campaign or widget'],
      ['02', 'Engage', 'Inbox + Copilot'],
      ['03', 'Qualify', 'Journey stage'],
      ['04', 'Convert', 'Catalog / booking'],
      ['05', 'Collect', 'M-Pesa / Paystack'],
      ['06', 'Retain', 'Reminder + rebuy'],
    ] as [$num, $title, $sub])
      <div class="flex items-center gap-3 rounded-2xl border border-gray-200 px-4 py-3" style="background:rgba(37,211,102,0.08);">
        <span class="font-display text-sm font-800" style="color:#25D366;">{{ $num }}</span>
        <div>
          <p class="text-sm font-semibold text-gray-900">{{ $title }}</p>
          <p class="text-xs text-gray-500">{{ $sub }}</p>
        </div>
      </div>
    @endforeach
  </div>
</div>
