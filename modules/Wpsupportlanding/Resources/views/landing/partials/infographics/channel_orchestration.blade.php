{{-- Multichannel orchestration diagram --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-gray-200 p-6 sm:p-8" style="background:linear-gradient(180deg,#FFF8EC 0%,#ffffff 100%);">
  <div class="flex flex-col lg:flex-row gap-8 items-center">
    <div class="lg:w-2/5">
      <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0E8A7A;">Channel orchestration</p>
      <h3 class="font-display text-3xl font-800 text-gray-900 mb-3">WhatsApp, SMS &amp; email campaigns</h3>
      <p class="text-base text-gray-600 leading-relaxed mb-4">One campaign engine fans out across three outreach channels — same audiences, segments, scheduling, and delivery analytics.</p>
      <ul class="space-y-2 text-base text-gray-600">
        <li class="flex gap-2"><span style="color:#0E8A7A;">→</span> File, group &amp; quick audiences</li>
        <li class="flex gap-2"><span style="color:#0E8A7A;">→</span> Unganisha SMS with optional Sender ID</li>
        <li class="flex gap-2"><span style="color:#0E8A7A;">→</span> Transparent rates from KES 0.6 per SMS</li>
        <li class="flex gap-2"><span style="color:#0E8A7A;">→</span> Timezone-aware scheduling</li>
        <li class="flex gap-2"><span style="color:#0E8A7A;">→</span> Pause, resume, and API-triggered sends</li>
      </ul>
    </div>

    <div class="lg:w-3/5 w-full">
      <svg viewBox="0 0 520 280" class="w-full h-auto" role="img" aria-label="Campaign engine broadcasting to WhatsApp, SMS, and email">
        <defs>
          <linearGradient id="chBeam" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#0E8A7A" stop-opacity="0.1"/>
            <stop offset="100%" stop-color="#0E8A7A" stop-opacity="0.6"/>
          </linearGradient>
        </defs>

        {{-- Source --}}
        <rect x="20" y="100" width="140" height="80" rx="16" fill="#ffffff" stroke="#0E8A7A" stroke-width="1.75"/>
        <text x="90" y="132" text-anchor="middle" fill="#0E8A7A" font-size="10" font-weight="700" font-family="Outfit,Plus Jakarta Sans,sans-serif">CAMPAIGN</text>
        <text x="90" y="152" text-anchor="middle" fill="#0f172a" font-size="14" font-weight="700" font-family="Outfit,Plus Jakarta Sans,sans-serif">ENGINE</text>

        {{-- Beams --}}
        <path d="M160 120 C220 90, 260 70, 320 60" stroke="url(#chBeam)" stroke-width="2" fill="none"/>
        <path d="M160 140 C230 140, 260 140, 320 140" stroke="url(#chBeam)" stroke-width="2" fill="none"/>
        <path d="M160 160 C220 190, 260 210, 320 220" stroke="url(#chBeam)" stroke-width="2" fill="none"/>

        {{-- Channels --}}
        <g>
          <rect x="320" y="30" width="170" height="60" rx="14" fill="#ffffff" stroke="rgba(14,138,122,0.35)" stroke-width="1.5"/>
          <text x="405" y="55" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Outfit,Plus Jakarta Sans,sans-serif">WhatsApp</text>
          <text x="405" y="72" text-anchor="middle" fill="#64748b" font-size="10">Templates · media · lists</text>
        </g>
        <g>
          <rect x="320" y="110" width="170" height="60" rx="14" fill="#ffffff" stroke="rgba(14,138,122,0.35)" stroke-width="1.5"/>
          <text x="405" y="135" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Outfit,Plus Jakarta Sans,sans-serif">SMS</text>
          <text x="405" y="152" text-anchor="middle" fill="#64748b" font-size="10">Sender ID · KES 0.6</text>
        </g>
        <g>
          <rect x="320" y="190" width="170" height="60" rx="14" fill="#ffffff" stroke="rgba(14,138,122,0.35)" stroke-width="1.5"/>
          <text x="405" y="215" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Outfit,Plus Jakarta Sans,sans-serif">Email</text>
          <text x="405" y="232" text-anchor="middle" fill="#64748b" font-size="10">SMTP · notices · digests</text>
        </g>
      </svg>
    </div>
  </div>
</div>
