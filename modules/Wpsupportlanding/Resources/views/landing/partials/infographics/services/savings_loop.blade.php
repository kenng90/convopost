{{-- Human-in-the-loop outcomes --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-slate-200 p-5 sm:p-8" style="background:linear-gradient(180deg,#f0f7f8 0%,#ffffff 100%);">
  <div class="relative">
    <div class="mb-6 max-w-2xl">
      <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0d9488;">Control</p>
      <h3 class="font-display text-2xl sm:text-3xl font-800 text-slate-900">AI suggests. Your team stays in control.</h3>
      <p class="text-base text-slate-600 mt-2">Accuracy, context, and accountability — not a bot that sends on its own.</p>
    </div>

    <svg viewBox="0 0 880 300" class="w-full h-auto" role="img" aria-label="Loop of prepare, review, approve, and log with source evidence">
      <defs>
        <marker id="loopArrow" markerWidth="7" markerHeight="7" refX="5" refY="3.5" orient="auto">
          <path d="M0,0 L7,3.5 L0,7 Z" fill="#0d9488"/>
        </marker>
      </defs>

      <circle cx="440" cy="150" r="78" fill="none" stroke="rgba(13,148,136,0.2)" stroke-width="2" stroke-dasharray="6 8"/>
      <circle cx="440" cy="150" r="48" fill="rgba(45,212,191,0.12)" stroke="#0d9488" stroke-width="1.5"/>
      <text x="440" y="145" text-anchor="middle" fill="#0d9488" font-size="11" font-weight="700" font-family="Sora,sans-serif">HUMAN</text>
      <text x="440" y="163" text-anchor="middle" fill="#0f172a" font-size="14" font-weight="800" font-family="Sora,sans-serif">Approves</text>

      @php
        $nodes = [
          [180, 70, 'Prepare', 'Draft + evidence'],
          [700, 70, 'Review', 'What needs a person'],
          [700, 230, 'Act', 'Send · file · update'],
          [180, 230, 'Log', 'Audit trail'],
        ];
      @endphp
      @foreach($nodes as [$x, $y, $t, $s])
        <g>
          <rect x="{{ $x - 70 }}" y="{{ $y - 32 }}" width="140" height="64" rx="16" fill="#ffffff" stroke="rgba(13,148,136,0.35)" stroke-width="1.5"/>
          <text x="{{ $x }}" y="{{ $y - 4 }}" text-anchor="middle" fill="#0f172a" font-size="14" font-weight="800" font-family="Sora,sans-serif">{{ $t }}</text>
          <text x="{{ $x }}" y="{{ $y + 16 }}" text-anchor="middle" fill="#64748b" font-size="11" font-family="Figtree,sans-serif">{{ $s }}</text>
        </g>
      @endforeach

      <path d="M250 70 C340 40, 540 40, 630 70" fill="none" stroke="#0d9488" stroke-width="1.8" marker-end="url(#loopArrow)"/>
      <path d="M700 102 C760 140, 760 160, 700 198" fill="none" stroke="#0d9488" stroke-width="1.8" marker-end="url(#loopArrow)"/>
      <path d="M630 230 C540 260, 340 260, 250 230" fill="none" stroke="#0d9488" stroke-width="1.8" marker-end="url(#loopArrow)"/>
      <path d="M180 198 C120 160, 120 140, 180 102" fill="none" stroke="#fb923c" stroke-width="1.8" marker-end="url(#loopArrow)"/>
    </svg>
  </div>
</div>
