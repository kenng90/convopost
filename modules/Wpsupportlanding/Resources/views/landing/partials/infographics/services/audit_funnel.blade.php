{{-- Process audit funnel infographic --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-slate-200 p-5 sm:p-8" style="background:linear-gradient(180deg,#f0f7f8 0%,#ffffff 100%);">
  <div class="relative">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-6">
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0d9488;">Discovery</p>
        <h3 class="font-display text-2xl sm:text-3xl font-800 text-slate-900">Business process audit</h3>
      </div>
      <p class="text-base text-slate-600 max-w-sm">Map work → find waste → score ROI → prioritize what to automate first.</p>
    </div>

    <svg viewBox="0 0 860 320" class="w-full h-auto" role="img" aria-label="Funnel showing process audit stages from workflow mapping to savings estimate">
      <defs>
        <linearGradient id="auditBand" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#2dd4bf" stop-opacity="0.35"/>
          <stop offset="100%" stop-color="#0d9488" stop-opacity="0.12"/>
        </linearGradient>
      </defs>

      {{-- Funnel shapes --}}
      <polygon points="80,40 780,40 700,100 160,100" fill="url(#auditBand)" stroke="#0d9488" stroke-width="1.5"/>
      <polygon points="160,115 700,115 630,175 230,175" fill="rgba(13,148,136,0.16)" stroke="#0d9488" stroke-width="1.5"/>
      <polygon points="230,190 630,190 560,250 300,250" fill="rgba(13,148,136,0.22)" stroke="#0d9488" stroke-width="1.5"/>
      <polygon points="300,265 560,265 510,305 350,305" fill="rgba(251,146,60,0.25)" stroke="#fb923c" stroke-width="1.5"/>

      <text x="430" y="78" text-anchor="middle" fill="#0f172a" font-size="15" font-weight="700" font-family="Sora,sans-serif">1 · Map current workflows</text>
      <text x="430" y="152" text-anchor="middle" fill="#0f172a" font-size="15" font-weight="700" font-family="Sora,sans-serif">2 · Flag repetitive &amp; error-prone tasks</text>
      <text x="430" y="227" text-anchor="middle" fill="#0f172a" font-size="15" font-weight="700" font-family="Sora,sans-serif">3 · Rank automation opportunities</text>
      <text x="430" y="292" text-anchor="middle" fill="#0f172a" font-size="14" font-weight="700" font-family="Sora,sans-serif">4 · Time &amp; cost savings estimate</text>

      {{-- Side callouts --}}
      <g>
        <rect x="20" y="130" width="110" height="54" rx="12" fill="#ffffff" stroke="rgba(13,148,136,0.3)"/>
        <text x="75" y="152" text-anchor="middle" fill="#0d9488" font-size="16" font-weight="800" font-family="Sora,sans-serif">12–40</text>
        <text x="75" y="170" text-anchor="middle" fill="#64748b" font-size="10" font-family="Figtree,sans-serif">hrs/week found</text>
      </g>
      <g>
        <rect x="730" y="130" width="110" height="54" rx="12" fill="#ffffff" stroke="rgba(251,146,60,0.4)"/>
        <text x="785" y="152" text-anchor="middle" fill="#ea580c" font-size="16" font-weight="800" font-family="Sora,sans-serif">ROI</text>
        <text x="785" y="170" text-anchor="middle" fill="#64748b" font-size="10" font-family="Figtree,sans-serif">scored backlog</text>
      </g>
    </svg>
  </div>
</div>
