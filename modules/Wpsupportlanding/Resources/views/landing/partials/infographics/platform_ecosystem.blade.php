{{-- Infobip-style platform ecosystem hub --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-gray-200 p-6 sm:p-10" style="background:linear-gradient(180deg,#f3faf6 0%,#ffffff 100%);">
  <div class="absolute inset-0 opacity-40" style="background-image:radial-gradient(rgba(37,211,102,0.12) 1px, transparent 1px);background-size:22px 22px;"></div>
  <div class="relative">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8">
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#25D366;">Platform map</p>
        <h3 class="font-display text-3xl sm:text-4xl font-800 text-gray-900">One hub. Every commerce signal.</h3>
      </div>
      <p class="text-base text-gray-600 max-w-sm">WhatsApp, Instagram &amp; Messenger sit at the center — campaigns, journeys, bookings, catalog, and collections orbit the same Customer 360.</p>
    </div>

    <svg viewBox="0 0 920 420" class="w-full h-auto" role="img" aria-label="ConvoConnect platform ecosystem diagram showing Meta messaging hub connected to inbox, journeys, bookings, catalog, campaigns, payments, flows, and APIs">
      <defs>
        <linearGradient id="ecoLine" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stop-color="#25D366" stop-opacity="0.15"/>
          <stop offset="50%" stop-color="#25D366" stop-opacity="0.55"/>
          <stop offset="100%" stop-color="#25D366" stop-opacity="0.15"/>
        </linearGradient>
        <filter id="ecoGlow" x="-20%" y="-20%" width="140%" height="140%">
          <feGaussianBlur stdDeviation="8" result="coloredBlur"/>
          <feMerge><feMergeNode in="coloredBlur"/><feMergeNode in="SourceGraphic"/></feMerge>
        </filter>
      </defs>

      {{-- Orbit rings --}}
      <circle cx="460" cy="210" r="155" fill="none" stroke="rgba(37,211,102,0.12)" stroke-width="1" stroke-dasharray="4 8"/>
      <circle cx="460" cy="210" r="105" fill="none" stroke="rgba(37,211,102,0.18)" stroke-width="1"/>

      {{-- Connection lines --}}
      <g stroke="url(#ecoLine)" stroke-width="1.5" fill="none">
        <path d="M460 210 L200 90"/>
        <path d="M460 210 L320 70"/>
        <path d="M460 210 L520 55"/>
        <path d="M460 210 L700 85"/>
        <path d="M460 210 L780 170"/>
        <path d="M460 210 L760 300"/>
        <path d="M460 210 L600 365"/>
        <path d="M460 210 L320 360"/>
        <path d="M460 210 L180 290"/>
        <path d="M460 210 L150 180"/>
      </g>

      {{-- Center hub --}}
      <g filter="url(#ecoGlow)">
        <circle cx="460" cy="210" r="58" fill="#ffffff" stroke="#25D366" stroke-width="2"/>
        <circle cx="460" cy="210" r="48" fill="rgba(37,211,102,0.12)" stroke="rgba(37,211,102,0.35)" stroke-width="1"/>
        <text x="460" y="198" text-anchor="middle" fill="#25D366" font-size="10" font-weight="700" font-family="Syne,sans-serif">WA · IG · MS</text>
        <text x="460" y="218" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Syne,sans-serif">INBOX HUB</text>
      </g>

      {{-- Satellite nodes --}}
      @php
        $nodes = [
          [200, 90, 'Team Inbox', 'WA · IG · Messenger'],
          [320, 70, 'Journeys', 'Kanban CRM'],
          [520, 55, 'Bookings', 'Calendar sync'],
          [700, 85, 'Catalog', 'In-chat shop'],
          [780, 170, 'Campaigns', 'WA · SMS · Email'],
          [760, 300, 'Collections', 'Retry · Paystack · chase'],
          [600, 365, 'Flows', '38+ nodes'],
          [320, 360, 'Forms', 'Native WA Flows'],
          [180, 290, 'Calling', 'Voice + AI'],
          [150, 180, 'API', 'Developer'],
        ];
      @endphp
      @foreach($nodes as [$x, $y, $title, $sub])
        <g>
          <rect x="{{ $x - 62 }}" y="{{ $y - 28 }}" width="124" height="56" rx="14" fill="#ffffff" stroke="rgba(37,211,102,0.28)" stroke-width="1.5"/>
          <text x="{{ $x }}" y="{{ $y - 4 }}" text-anchor="middle" fill="#0f172a" font-size="12" font-weight="700" font-family="Syne,sans-serif">{{ $title }}</text>
          <text x="{{ $x }}" y="{{ $y + 14 }}" text-anchor="middle" fill="#64748b" font-size="10" font-family="DM Sans,sans-serif">{{ $sub }}</text>
        </g>
      @endforeach
    </svg>
  </div>
</div>
