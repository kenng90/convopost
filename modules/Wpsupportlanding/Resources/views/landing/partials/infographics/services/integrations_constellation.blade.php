{{-- Integrations constellation --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-slate-200 p-5 sm:p-8" style="background:linear-gradient(180deg,#f0f7f8 0%,#ffffff 100%);">
  <div class="absolute inset-0 opacity-40" style="background-image:radial-gradient(rgba(13,148,136,0.12) 1px, transparent 1px);background-size:22px 22px;"></div>
  <div class="relative">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0d9488;">Stack-agnostic</p>
        <h3 class="font-display text-2xl sm:text-3xl font-800 text-slate-900">Built around the tools you already use</h3>
      </div>
      <p class="text-base text-slate-600 max-w-sm">The AI layer sits in the middle — email, documents, chat, voice, CRM, and payments. Cloud or on-network.</p>
    </div>

    <svg viewBox="0 0 920 380" class="w-full h-auto" role="img" aria-label="Constellation of email, documents, chat, voice, and business tools connected through an AI operating layer">
      <defs>
        <linearGradient id="intLine" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stop-color="#0d9488" stop-opacity="0.15"/>
          <stop offset="50%" stop-color="#0d9488" stop-opacity="0.55"/>
          <stop offset="100%" stop-color="#0d9488" stop-opacity="0.15"/>
        </linearGradient>
      </defs>

      <circle cx="460" cy="190" r="140" fill="none" stroke="rgba(13,148,136,0.12)" stroke-width="1" stroke-dasharray="4 8"/>
      <circle cx="460" cy="190" r="95" fill="none" stroke="rgba(13,148,136,0.18)" stroke-width="1"/>

      <g stroke="url(#intLine)" stroke-width="1.5" fill="none">
        <path d="M460 190 L200 80"/>
        <path d="M460 190 L340 55"/>
        <path d="M460 190 L560 50"/>
        <path d="M460 190 L720 85"/>
        <path d="M460 190 L800 170"/>
        <path d="M460 190 L780 280"/>
        <path d="M460 190 L620 340"/>
        <path d="M460 190 L320 345"/>
        <path d="M460 190 L170 270"/>
        <path d="M460 190 L145 160"/>
        <path d="M460 190 L250 320"/>
        <path d="M460 190 L700 320"/>
      </g>

      <circle cx="460" cy="190" r="52" fill="#ffffff" stroke="#0d9488" stroke-width="2"/>
      <circle cx="460" cy="190" r="42" fill="rgba(45,212,191,0.12)"/>
      <text x="460" y="184" text-anchor="middle" fill="#0d9488" font-size="10" font-weight="700" font-family="Sora,sans-serif">HUB</text>
      <text x="460" y="202" text-anchor="middle" fill="#0f172a" font-size="12" font-weight="700" font-family="Sora,sans-serif">AI layer</text>

      @php
        $tools = [
          [200, 80, 'Email'],
          [340, 55, 'Documents'],
          [560, 50, 'WhatsApp'],
          [720, 85, 'Voice'],
          [800, 170, 'CRM'],
          [780, 280, 'Sheets'],
          [620, 340, 'Payments'],
          [320, 345, 'Bookings'],
          [170, 270, 'Accounting'],
          [145, 160, 'Drive'],
          [250, 320, 'Tickets'],
          [700, 320, 'Human inbox'],
        ];
      @endphp
      @foreach($tools as [$x, $y, $label])
        <g>
          <rect x="{{ $x - 54 }}" y="{{ $y - 18 }}" width="108" height="36" rx="12" fill="#ffffff" stroke="rgba(13,148,136,0.28)" stroke-width="1.4"/>
          <text x="{{ $x }}" y="{{ $y + 5 }}" text-anchor="middle" fill="#0f172a" font-size="12" font-weight="700" font-family="Sora,sans-serif">{{ $label }}</text>
        </g>
      @endforeach
    </svg>
  </div>
</div>
