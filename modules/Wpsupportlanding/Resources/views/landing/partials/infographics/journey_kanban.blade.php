{{-- Journey pipeline kanban infographic --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-gray-200 p-5 sm:p-7" style="background:linear-gradient(180deg,#f3faf6 0%,#ffffff 100%);">
  <div class="flex items-center justify-between mb-5">
    <div>
      <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-1" style="color:#25D366;">Journey Pipelines</p>
      <p class="text-base text-gray-600">Stage-triggered WhatsApp campaigns on every move</p>
    </div>
    <span class="badge text-xs">Live board</span>
  </div>

  <svg viewBox="0 0 720 260" class="w-full h-auto" role="img" aria-label="Kanban journey pipeline with Lead, Qualified, Proposal, and Won stages">
    {{-- Stage columns --}}
    @php
      $cols = [
        [30, 'Lead', '12', '#64748b', [['Amina K.', 'New inquiry'], ['Brian O.', 'Catalog ask']]],
        [200, 'Qualified', '8', '#3b82f6', [['Faith W.', 'Budget ok'], ['James M.', 'Demo done']]],
        [370, 'Proposal', '5', '#d97706', [['Grace N.', 'Quote sent']]],
        [540, 'Won', '3', '#25D366', [['Leo T.', 'Paid ✓']]],
      ];
    @endphp

    {{-- Flow arrows between columns --}}
    <path d="M175 40 H195" stroke="rgba(37,211,102,0.4)" stroke-width="2" marker-end="url(#jpArrow)"/>
    <path d="M345 40 H365" stroke="rgba(37,211,102,0.4)" stroke-width="2"/>
    <path d="M515 40 H535" stroke="rgba(37,211,102,0.4)" stroke-width="2"/>
    <defs>
      <marker id="jpArrow" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto">
        <path d="M0,0 L6,3 L0,6 Z" fill="#25D366"/>
      </marker>
    </defs>

    @foreach($cols as [$x, $name, $count, $color, $cards])
      <g>
        <rect x="{{ $x }}" y="16" width="150" height="228" rx="16" fill="#f8fbf9" stroke="rgba(7,94,84,0.12)"/>
        <circle cx="{{ $x + 18 }}" cy="38" r="5" fill="{{ $color }}"/>
        <text x="{{ $x + 30 }}" y="42" fill="#475569" font-size="12" font-weight="700" font-family="Syne,sans-serif">{{ $name }}</text>
        <rect x="{{ $x + 110 }}" y="26" width="28" height="20" rx="10" fill="{{ $color }}22"/>
        <text x="{{ $x + 124 }}" y="40" text-anchor="middle" fill="{{ $color }}" font-size="11" font-weight="700" font-family="DM Sans,sans-serif">{{ $count }}</text>

        @foreach($cards as $i => [$who, $note])
          <rect x="{{ $x + 10 }}" y="{{ 60 + $i * 70 }}" width="130" height="58" rx="10" fill="#f3faf6" stroke="rgba(7,94,84,0.1)"/>
          <text x="{{ $x + 22 }}" y="{{ 84 + $i * 70 }}" fill="#0f172a" font-size="11" font-weight="600" font-family="DM Sans,sans-serif">{{ $who }}</text>
          <text x="{{ $x + 22 }}" y="{{ 102 + $i * 70 }}" fill="#64748b" font-size="10" font-family="DM Sans,sans-serif">{{ $note }}</text>
        @endforeach
      </g>
    @endforeach

    {{-- Campaign pulse under Won --}}
    <rect x="540" y="200" width="150" height="32" rx="8" fill="rgba(37,211,102,0.1)" stroke="rgba(37,211,102,0.3)"/>
    <text x="615" y="220" text-anchor="middle" fill="#25D366" font-size="10" font-weight="600" font-family="DM Sans,sans-serif">Campaign fired →</text>
  </svg>
</div>
