{{-- Deploy first, then productize — replaces priced packages --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-slate-200 p-5 sm:p-8" style="background:linear-gradient(180deg,#f0f7f8 0%,#ffffff 100%);">
  <div class="relative">
    <div class="mb-8">
      <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0d9488;">Motion</p>
      <h3 class="font-display text-2xl sm:text-3xl font-800 text-slate-900">We deploy first. Then we productize what repeats.</h3>
      <p class="text-base text-slate-600 mt-2 max-w-2xl">There is a gap between what AI can do and how a normal business actually operates. We close it inside your company, then turn repeated workflows into infrastructure.</p>
    </div>

    <div class="grid sm:grid-cols-3 gap-4">
      @foreach([
        ['Deploy', 'One workflow in the tools you already use, with approval gates from day one.'],
        ['Expand', 'The context layer learns the operation. More workflows share the same memory.'],
        ['Productize', 'Patterns that repeat become product — not another one-off freelance build.'],
      ] as [$title, $body])
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
          <p class="font-display text-xl font-800 text-slate-900 mb-2">{{ $title }}</p>
          <p class="text-sm text-slate-600 leading-relaxed">{{ $body }}</p>
        </div>
      @endforeach
    </div>

    <svg viewBox="0 0 880 90" class="w-full h-auto mt-8 hidden sm:block" aria-hidden="true">
      <line x1="80" y1="45" x2="800" y2="45" stroke="rgba(13,148,136,0.25)" stroke-width="3"/>
      <circle cx="140" cy="45" r="10" fill="#0d9488"/>
      <circle cx="440" cy="45" r="14" fill="#0d9488"/>
      <circle cx="740" cy="45" r="10" fill="#fb923c"/>
      <text x="140" y="78" text-anchor="middle" fill="#64748b" font-size="12" font-family="Figtree,sans-serif">First workflow live</text>
      <text x="440" y="78" text-anchor="middle" fill="#0f172a" font-size="12" font-weight="700" font-family="Figtree,sans-serif">Operating system</text>
      <text x="740" y="78" text-anchor="middle" fill="#64748b" font-size="12" font-family="Figtree,sans-serif">Reusable product</text>
    </svg>
  </div>
</div>
