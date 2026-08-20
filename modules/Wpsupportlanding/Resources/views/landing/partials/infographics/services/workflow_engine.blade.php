{{-- Workflow automation engine diagram --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-slate-200 p-5 sm:p-8" style="background:linear-gradient(180deg,#f0f7f8 0%,#ffffff 100%);">
  <div class="relative">
    <div class="mb-6">
      <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0d9488;">Build</p>
      <h3 class="font-display text-2xl sm:text-3xl font-800 text-slate-900">Workflow automation engine</h3>
      <p class="text-base text-slate-600 mt-2 max-w-2xl">Triggers, logic, and actions — wired across the tools your team already uses.</p>
    </div>

    <svg viewBox="0 0 900 360" class="w-full h-auto" role="img" aria-label="Flow diagram of trigger, logic, action, and notify steps in a business automation">
      <defs>
        <marker id="arrowSvc" markerWidth="8" markerHeight="8" refX="6" refY="4" orient="auto">
          <path d="M0,0 L8,4 L0,8 Z" fill="#0d9488"/>
        </marker>
        <linearGradient id="nodePulse" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#ccfbf1"/>
          <stop offset="100%" stop-color="#ffffff"/>
        </linearGradient>
      </defs>

      {{-- Swim lanes --}}
      <text x="70" y="28" fill="#94a3b8" font-size="11" font-weight="700" font-family="Sora,sans-serif" letter-spacing="1">TRIGGER</text>
      <text x="290" y="28" fill="#94a3b8" font-size="11" font-weight="700" font-family="Sora,sans-serif" letter-spacing="1">LOGIC</text>
      <text x="510" y="28" fill="#94a3b8" font-size="11" font-weight="700" font-family="Sora,sans-serif" letter-spacing="1">ACTION</text>
      <text x="730" y="28" fill="#94a3b8" font-size="11" font-weight="700" font-family="Sora,sans-serif" letter-spacing="1">OUTCOME</text>

      {{-- Trigger nodes --}}
      <rect x="20" y="50" width="160" height="56" rx="14" fill="url(#nodePulse)" stroke="#0d9488" stroke-width="1.5"/>
      <text x="100" y="74" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">New lead</text>
      <text x="100" y="92" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">Form · CRM · Chat</text>

      <rect x="20" y="130" width="160" height="56" rx="14" fill="#ffffff" stroke="rgba(13,148,136,0.35)" stroke-width="1.5"/>
      <text x="100" y="154" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Approval request</text>
      <text x="100" y="172" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">Invoice · Leave · PO</text>

      <rect x="20" y="210" width="160" height="56" rx="14" fill="#ffffff" stroke="rgba(13,148,136,0.35)" stroke-width="1.5"/>
      <text x="100" y="234" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Schedule</text>
      <text x="100" y="252" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">Daily · Weekly</text>

      {{-- Logic diamond-ish --}}
      <rect x="240" y="120" width="160" height="90" rx="18" fill="#0f172a"/>
      <text x="320" y="155" text-anchor="middle" fill="#2dd4bf" font-size="12" font-weight="700" font-family="Sora,sans-serif">IF / ELSE</text>
      <text x="320" y="175" text-anchor="middle" fill="#f8fafc" font-size="13" font-weight="700" font-family="Sora,sans-serif">Rules &amp; routing</text>
      <text x="320" y="193" text-anchor="middle" fill="#94a3b8" font-size="11" font-family="Figtree,sans-serif">Owner · Amount · SLA</text>

      {{-- Actions --}}
      <rect x="460" y="50" width="160" height="56" rx="14" fill="#ffffff" stroke="rgba(251,146,60,0.45)" stroke-width="1.5"/>
      <text x="540" y="74" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Assign + notify</text>
      <text x="540" y="92" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">Slack · Email · WA</text>

      <rect x="460" y="130" width="160" height="56" rx="14" fill="#ffffff" stroke="rgba(251,146,60,0.45)" stroke-width="1.5"/>
      <text x="540" y="154" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Create document</text>
      <text x="540" y="172" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">Proposal · Contract</text>

      <rect x="460" y="210" width="160" height="56" rx="14" fill="#ffffff" stroke="rgba(251,146,60,0.45)" stroke-width="1.5"/>
      <text x="540" y="234" text-anchor="middle" fill="#0f172a" font-size="13" font-weight="700" font-family="Sora,sans-serif">Update records</text>
      <text x="540" y="252" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">CRM · Sheets · ERP</text>

      {{-- Outcomes --}}
      <rect x="680" y="90" width="180" height="140" rx="20" fill="rgba(45,212,191,0.12)" stroke="#0d9488" stroke-width="1.8"/>
      <text x="770" y="130" text-anchor="middle" fill="#0d9488" font-size="12" font-weight="700" font-family="Sora,sans-serif">ALWAYS ON</text>
      <text x="770" y="158" text-anchor="middle" fill="#0f172a" font-size="16" font-weight="800" font-family="Sora,sans-serif">Hours saved</text>
      <text x="770" y="182" text-anchor="middle" fill="#0f172a" font-size="16" font-weight="800" font-family="Sora,sans-serif">Fewer errors</text>
      <text x="770" y="206" text-anchor="middle" fill="#0f172a" font-size="16" font-weight="800" font-family="Sora,sans-serif">Faster handoffs</text>

      {{-- Connectors --}}
      <line x1="180" y1="78" x2="240" y2="150" stroke="#0d9488" stroke-width="1.5" marker-end="url(#arrowSvc)"/>
      <line x1="180" y1="158" x2="240" y2="165" stroke="#0d9488" stroke-width="1.5" marker-end="url(#arrowSvc)"/>
      <line x1="180" y1="238" x2="240" y2="185" stroke="#0d9488" stroke-width="1.5" marker-end="url(#arrowSvc)"/>
      <line x1="400" y1="145" x2="460" y2="78" stroke="#fb923c" stroke-width="1.5" marker-end="url(#arrowSvc)"/>
      <line x1="400" y1="165" x2="460" y2="158" stroke="#fb923c" stroke-width="1.5" marker-end="url(#arrowSvc)"/>
      <line x1="400" y1="185" x2="460" y2="238" stroke="#fb923c" stroke-width="1.5" marker-end="url(#arrowSvc)"/>
      <line x1="620" y1="158" x2="680" y2="160" stroke="#0d9488" stroke-width="2" marker-end="url(#arrowSvc)"/>

      {{-- Example chips --}}
      <text x="20" y="320" fill="#64748b" font-size="12" font-family="Figtree,sans-serif">Examples: invoice approvals · lead follow-ups · booking confirmations · payment reminders</text>
    </svg>
  </div>
</div>
