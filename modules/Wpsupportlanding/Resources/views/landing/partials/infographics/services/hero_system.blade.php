{{-- Hero system diagram — manual work in → automated ops out --}}
<div class="infographic-panel relative overflow-hidden rounded-[2rem] border border-white/10 p-5 sm:p-8" style="background:linear-gradient(165deg,#0d1a2e 0%,#122438 55%,#0a1424 100%);">
  <div class="absolute inset-0 opacity-30" style="background-image:radial-gradient(rgba(45,212,191,0.18) 1px, transparent 1px);background-size:20px 20px;"></div>
  <div class="absolute -top-20 right-0 w-72 h-72 rounded-full blur-3xl opacity-30" style="background:#2dd4bf;"></div>
  <div class="absolute bottom-0 -left-10 w-56 h-56 rounded-full blur-3xl opacity-20" style="background:#fb923c;"></div>

  <div class="relative">
    <div class="flex items-center justify-between gap-3 mb-6">
      <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color:#2dd4bf;">Live process map</p>
      <span class="text-[11px] px-2.5 py-1 rounded-full border border-white/10 text-slate-300">Audit → Build → Run</span>
    </div>

    <svg viewBox="0 0 880 420" class="w-full h-auto" role="img" aria-label="Diagram showing manual business processes flowing through Convocon automation into connected tools and outcomes">
      <defs>
        <linearGradient id="svcHeroFlow" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stop-color="#fb923c" stop-opacity="0.2"/>
          <stop offset="45%" stop-color="#2dd4bf" stop-opacity="0.85"/>
          <stop offset="100%" stop-color="#38bdf8" stop-opacity="0.35"/>
        </linearGradient>
        <filter id="svcHeroGlow" x="-30%" y="-30%" width="160%" height="160%">
          <feGaussianBlur stdDeviation="6" result="b"/>
          <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
        </filter>
      </defs>

      {{-- Input column --}}
      <text x="70" y="36" fill="#94a3b8" font-size="11" font-weight="600" font-family="Sora,sans-serif" letter-spacing="1.5">BEFORE</text>
      @php
        $inputs = [
          [70, 70, 'Lead follow-ups', 'Manual chasing'],
          [70, 145, 'Approvals', 'Email threads'],
          [70, 220, 'Onboarding', 'Copy-paste'],
          [70, 295, 'Reports', 'Spreadsheet grind'],
        ];
      @endphp
      @foreach($inputs as [$x, $y, $t, $s])
        <g>
          <rect x="{{ $x }}" y="{{ $y }}" width="168" height="58" rx="14" fill="rgba(255,255,255,0.04)" stroke="rgba(251,146,60,0.35)" stroke-width="1.2"/>
          <circle cx="{{ $x + 22 }}" cy="{{ $y + 29 }}" r="6" fill="#fb923c" opacity="0.85"/>
          <text x="{{ $x + 40 }}" y="{{ $y + 26 }}" fill="#f8fafc" font-size="13" font-weight="700" font-family="Sora,sans-serif">{{ $t }}</text>
          <text x="{{ $x + 40 }}" y="{{ $y + 44 }}" fill="#94a3b8" font-size="11" font-family="Figtree,sans-serif">{{ $s }}</text>
        </g>
      @endforeach

      {{-- Flow lines into core --}}
      <g stroke="url(#svcHeroFlow)" stroke-width="1.8" fill="none">
        <path d="M238 99 C300 99, 320 190, 390 210" stroke-dasharray="5 6">
          <animate attributeName="stroke-dashoffset" from="40" to="0" dur="2.4s" repeatCount="indefinite"/>
        </path>
        <path d="M238 174 C310 174, 330 200, 390 210" stroke-dasharray="5 6">
          <animate attributeName="stroke-dashoffset" from="40" to="0" dur="2.6s" repeatCount="indefinite"/>
        </path>
        <path d="M238 249 C310 249, 330 225, 390 210" stroke-dasharray="5 6">
          <animate attributeName="stroke-dashoffset" from="40" to="0" dur="2.2s" repeatCount="indefinite"/>
        </path>
        <path d="M238 324 C300 324, 320 240, 390 210" stroke-dasharray="5 6">
          <animate attributeName="stroke-dashoffset" from="40" to="0" dur="2.8s" repeatCount="indefinite"/>
        </path>
      </g>

      {{-- Core engine --}}
      <g filter="url(#svcHeroGlow)">
        <rect x="390" y="145" width="200" height="130" rx="24" fill="#102033" stroke="#2dd4bf" stroke-width="2"/>
        <rect x="402" y="157" width="176" height="106" rx="18" fill="rgba(45,212,191,0.08)" stroke="rgba(45,212,191,0.25)"/>
        <text x="490" y="195" text-anchor="middle" fill="#2dd4bf" font-size="11" font-weight="700" font-family="Sora,sans-serif" letter-spacing="2">CONVOCON</text>
        <text x="490" y="220" text-anchor="middle" fill="#f8fafc" font-size="18" font-weight="700" font-family="Sora,sans-serif">Automation</text>
        <text x="490" y="242" text-anchor="middle" fill="#94a3b8" font-size="12" font-family="Figtree,sans-serif">Practice</text>
      </g>

      {{-- Output column --}}
      <text x="660" y="36" fill="#94a3b8" font-size="11" font-weight="600" font-family="Sora,sans-serif" letter-spacing="1.5">AFTER</text>
      @php
        $outputs = [
          [660, 70, 'CRM synced', 'Leads assigned'],
          [660, 145, 'Workflows live', 'Approvals auto'],
          [660, 220, 'AI replies', 'Tickets routed'],
          [660, 295, 'Dashboards', 'Weekly reports'],
        ];
      @endphp
      @foreach($outputs as [$x, $y, $t, $s])
        <g>
          <rect x="{{ $x }}" y="{{ $y }}" width="168" height="58" rx="14" fill="rgba(45,212,191,0.08)" stroke="rgba(45,212,191,0.4)" stroke-width="1.2"/>
          <circle cx="{{ $x + 22 }}" cy="{{ $y + 29 }}" r="6" fill="#2dd4bf"/>
          <text x="{{ $x + 40 }}" y="{{ $y + 26 }}" fill="#f8fafc" font-size="13" font-weight="700" font-family="Sora,sans-serif">{{ $t }}</text>
          <text x="{{ $x + 40 }}" y="{{ $y + 44 }}" fill="#94a3b8" font-size="11" font-family="Figtree,sans-serif">{{ $s }}</text>
        </g>
      @endforeach

      <g stroke="url(#svcHeroFlow)" stroke-width="1.8" fill="none">
        <path d="M590 210 C630 210, 640 99, 660 99"/>
        <path d="M590 210 C630 210, 640 174, 660 174"/>
        <path d="M590 210 C630 210, 640 249, 660 249"/>
        <path d="M590 210 C630 210, 640 324, 660 324"/>
      </g>

      <text x="440" y="400" fill="#64748b" font-size="12" font-family="Figtree,sans-serif">Tool-agnostic · Zapier · Make · n8n · Power Automate · Custom</text>
    </svg>
  </div>
</div>
