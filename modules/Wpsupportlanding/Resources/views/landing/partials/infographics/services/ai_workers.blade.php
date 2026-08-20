{{-- Six-step operating loop: event → understand → surface → approve → log → act --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-slate-200 p-5 sm:p-8" style="background:linear-gradient(180deg,#f0f7f8 0%,#ffffff 100%);">
  <div class="relative">
    <div class="mb-6 max-w-2xl">
      <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0d9488;">The loop</p>
      <h3 class="font-display text-2xl sm:text-3xl font-800 text-slate-900">AI prepares. Your team approves.</h3>
      <p class="text-base text-slate-600 mt-2">Nothing sends or writes until a person signs off. Every action keeps the source evidence.</p>
    </div>

    <svg viewBox="0 0 900 340" class="w-full h-auto" role="img" aria-label="Six-step loop from source event through AI understanding, human approval, and workflow action">
      <defs>
        <marker id="osArrow" markerWidth="8" markerHeight="8" refX="6" refY="4" orient="auto">
          <path d="M0,0 L8,4 L0,8 Z" fill="#0d9488"/>
        </marker>
      </defs>

      @php
        $steps = [
          [30, 40, '01', 'Source event', 'Email, chat, invoice, call'],
          [320, 40, '02', 'AI understanding', 'Read against your context'],
          [610, 40, '03', 'What needs attention', 'Changes, gaps, next action'],
          [30, 190, '06', 'Workflow action', 'Follow-up, report, record'],
          [320, 190, '05', 'Operational record', 'Logged with evidence'],
          [610, 190, '04', 'Human approval', 'Review before anything moves'],
        ];
      @endphp
      @foreach($steps as [$x, $y, $n, $t, $s])
        <g>
          <rect x="{{ $x }}" y="{{ $y }}" width="260" height="100" rx="20" fill="#ffffff" stroke="{{ $n === '04' ? '#fb923c' : 'rgba(13,148,136,0.35)' }}" stroke-width="{{ $n === '04' ? '1.8' : '1.5' }}"/>
          <text x="{{ $x + 24 }}" y="{{ $y + 36 }}" fill="#0d9488" font-size="12" font-weight="700" font-family="Sora,sans-serif">{{ $n }}</text>
          <text x="{{ $x + 24 }}" y="{{ $y + 60 }}" fill="#0f172a" font-size="16" font-weight="800" font-family="Sora,sans-serif">{{ $t }}</text>
          <text x="{{ $x + 24 }}" y="{{ $y + 82 }}" fill="#64748b" font-size="12" font-family="Figtree,sans-serif">{{ $s }}</text>
        </g>
      @endforeach

      <line x1="290" y1="90" x2="320" y2="90" stroke="#0d9488" stroke-width="1.8" marker-end="url(#osArrow)"/>
      <line x1="580" y1="90" x2="610" y2="90" stroke="#0d9488" stroke-width="1.8" marker-end="url(#osArrow)"/>
      <line x1="740" y1="140" x2="740" y2="190" stroke="#fb923c" stroke-width="1.8" marker-end="url(#osArrow)"/>
      <line x1="610" y1="240" x2="580" y2="240" stroke="#0d9488" stroke-width="1.8" marker-end="url(#osArrow)"/>
      <line x1="320" y1="240" x2="290" y2="240" stroke="#0d9488" stroke-width="1.8" marker-end="url(#osArrow)"/>
    </svg>
  </div>
</div>
