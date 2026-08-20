{{-- On-network as a control, not the product --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-slate-200 p-5 sm:p-8" style="background:linear-gradient(180deg,#f0f7f8 0%,#ffffff 100%);">
  <div class="relative">
    <div class="mb-6 max-w-2xl">
      <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0d9488;">Boundaries</p>
      <h3 class="font-display text-2xl sm:text-3xl font-800 text-slate-900">Workspace-level data boundaries</h3>
      <p class="text-base text-slate-600 mt-2">Cloud is the default. When customer data cannot leave the building, the same operating system can run inference on your network.</p>
    </div>

    <svg viewBox="0 0 900 300" class="w-full h-auto" role="img" aria-label="Side by side of cloud inference versus on-network inference for the same AI operating system">
      <rect x="20" y="20" width="410" height="250" rx="24" fill="#ffffff" stroke="rgba(13,148,136,0.28)" stroke-width="1.5"/>
      <text x="225" y="52" text-anchor="middle" fill="#0d9488" font-size="11" font-weight="700" font-family="Sora,sans-serif" letter-spacing="1.5">DEFAULT</text>
      <text x="225" y="78" text-anchor="middle" fill="#0f172a" font-size="18" font-weight="800" font-family="Sora,sans-serif">Cloud layer</text>
      <text x="225" y="102" text-anchor="middle" fill="#64748b" font-size="12" font-family="Figtree,sans-serif">Fast to deploy · same approval gates</text>

      <rect x="70" y="128" width="310" height="44" rx="12" fill="rgba(45,212,191,0.12)"/>
      <text x="225" y="156" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Your context → hosted model → review</text>

      <text x="225" y="200" text-anchor="middle" fill="#64748b" font-size="12" font-family="Figtree,sans-serif">Best when data can leave the office</text>
      <text x="225" y="222" text-anchor="middle" fill="#64748b" font-size="12" font-family="Figtree,sans-serif">Source-backed · human approval</text>

      <rect x="470" y="20" width="410" height="250" rx="24" fill="#0f172a"/>
      <text x="675" y="52" text-anchor="middle" fill="#2dd4bf" font-size="11" font-weight="700" font-family="Sora,sans-serif" letter-spacing="1.5">WHEN REQUIRED</text>
      <text x="675" y="78" text-anchor="middle" fill="#f8fafc" font-size="18" font-weight="800" font-family="Sora,sans-serif">On-network layer</text>
      <text x="675" y="102" text-anchor="middle" fill="#94a3b8" font-size="12" font-family="Figtree,sans-serif">Docs &amp; chats stay on your LAN</text>

      <rect x="520" y="128" width="310" height="44" rx="12" fill="rgba(45,212,191,0.12)" stroke="#2dd4bf" stroke-width="1"/>
      <text x="675" y="156" text-anchor="middle" fill="#f8fafc" font-size="13" font-weight="700" font-family="Sora,sans-serif">Your context → local model → review</text>

      <text x="675" y="200" text-anchor="middle" fill="#94a3b8" font-size="12" font-family="Figtree,sans-serif">Clinics · schools · regulated ops</text>
      <text x="675" y="222" text-anchor="middle" fill="#94a3b8" font-size="12" font-family="Figtree,sans-serif">Same playbook · private inference</text>
    </svg>
  </div>
</div>
