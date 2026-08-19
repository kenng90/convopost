{{-- AI worker loop: query → retrieve local data → act → handoff --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-slate-200 p-5 sm:p-8" style="background:linear-gradient(180deg,#f0f7f8 0%,#ffffff 100%);">
  <div class="relative">
    <div class="mb-6 max-w-2xl">
      <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0d9488;">Workers</p>
      <h3 class="font-display text-2xl sm:text-3xl font-800 text-slate-900">An AI worker that uses your data</h3>
      <p class="text-base text-slate-600 mt-2">Retrieval over your SOPs, catalogs, and chats — then an action or a human. Not a generic chatbot.</p>
    </div>

    <svg viewBox="0 0 900 340" class="w-full h-auto" role="img" aria-label="Flow of an AI worker answering from company knowledge then taking an action or handing off to a human">
      <defs>
        <marker id="workerArrow" markerWidth="8" markerHeight="8" refX="6" refY="4" orient="auto">
          <path d="M0,0 L8,4 L0,8 Z" fill="#0d9488"/>
        </marker>
      </defs>

      <text x="80" y="28" fill="#94a3b8" font-size="11" font-weight="700" font-family="Sora,sans-serif" letter-spacing="1">INBOUND</text>
      <text x="300" y="28" fill="#94a3b8" font-size="11" font-weight="700" font-family="Sora,sans-serif" letter-spacing="1">YOUR DATA</text>
      <text x="530" y="28" fill="#94a3b8" font-size="11" font-weight="700" font-family="Sora,sans-serif" letter-spacing="1">WORKER</text>
      <text x="750" y="28" fill="#94a3b8" font-size="11" font-weight="700" font-family="Sora,sans-serif" letter-spacing="1">NEXT STEP</text>

      <rect x="20" y="50" width="160" height="56" rx="14" fill="#ffffff" stroke="#0d9488" stroke-width="1.5"/>
      <text x="100" y="74" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Chat query</text>
      <text x="100" y="92" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">WhatsApp · web</text>

      <rect x="20" y="130" width="160" height="56" rx="14" fill="#ffffff" stroke="rgba(13,148,136,0.35)" stroke-width="1.5"/>
      <text x="100" y="154" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Inbound call</text>
      <text x="100" y="172" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">Voice · WhatsApp</text>

      <rect x="20" y="210" width="160" height="56" rx="14" fill="#ffffff" stroke="rgba(13,148,136,0.35)" stroke-width="1.5"/>
      <text x="100" y="234" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Internal ask</text>
      <text x="100" y="252" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">Staff · SOP lookup</text>

      <rect x="230" y="110" width="170" height="110" rx="18" fill="#ffffff" stroke="rgba(13,148,136,0.4)" stroke-width="1.5"/>
      <text x="315" y="145" text-anchor="middle" fill="#0d9488" font-size="11" font-weight="700" font-family="Sora,sans-serif">RETRIEVE</text>
      <text x="315" y="168" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Knowledge · catalogs</text>
      <text x="315" y="188" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">Past chats · SOPs</text>
      <text x="315" y="206" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">No generic guesses</text>

      <rect x="450" y="105" width="170" height="120" rx="20" fill="#0f172a"/>
      <text x="535" y="145" text-anchor="middle" fill="#2dd4bf" font-size="11" font-weight="700" font-family="Sora,sans-serif">AI WORKER</text>
      <text x="535" y="168" text-anchor="middle" fill="#f8fafc" font-size="14" font-weight="700" font-family="Sora,sans-serif">Answer or act</text>
      <text x="535" y="188" text-anchor="middle" fill="#94a3b8" font-size="11" font-family="Figtree,sans-serif">Playbook limits</text>
      <text x="535" y="206" text-anchor="middle" fill="#94a3b8" font-size="11" font-family="Figtree,sans-serif">Handoff when unsure</text>

      <rect x="680" y="50" width="180" height="56" rx="14" fill="#ffffff" stroke="rgba(251,146,60,0.45)" stroke-width="1.5"/>
      <text x="770" y="74" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Trigger action</text>
      <text x="770" y="92" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">Pay · book · ticket</text>

      <rect x="680" y="130" width="180" height="56" rx="14" fill="rgba(45,212,191,0.12)" stroke="#0d9488" stroke-width="1.5"/>
      <text x="770" y="154" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Reply from knowledge</text>
      <text x="770" y="172" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">Cited · in your voice</text>

      <rect x="680" y="210" width="180" height="56" rx="14" fill="#ffffff" stroke="rgba(251,146,60,0.45)" stroke-width="1.5"/>
      <text x="770" y="234" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Human handoff</text>
      <text x="770" y="252" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">Brief already logged</text>

      <line x1="180" y1="78" x2="230" y2="150" stroke="#0d9488" stroke-width="1.5" marker-end="url(#workerArrow)"/>
      <line x1="180" y1="158" x2="230" y2="165" stroke="#0d9488" stroke-width="1.5" marker-end="url(#workerArrow)"/>
      <line x1="180" y1="238" x2="230" y2="185" stroke="#0d9488" stroke-width="1.5" marker-end="url(#workerArrow)"/>
      <line x1="400" y1="165" x2="450" y2="165" stroke="#0d9488" stroke-width="2" marker-end="url(#workerArrow)"/>
      <line x1="620" y1="145" x2="680" y2="78" stroke="#fb923c" stroke-width="1.5" marker-end="url(#workerArrow)"/>
      <line x1="620" y1="165" x2="680" y2="158" stroke="#0d9488" stroke-width="1.5" marker-end="url(#workerArrow)"/>
      <line x1="620" y1="185" x2="680" y2="238" stroke="#fb923c" stroke-width="1.5" marker-end="url(#workerArrow)"/>

      <text x="20" y="320" fill="#64748b" font-size="12" font-family="Figtree,sans-serif">Cloud by default · on-network inference when customer data cannot leave the building</text>
    </svg>
  </div>
</div>
