{{-- Package ladder infographic --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-slate-200 p-5 sm:p-8" style="background:linear-gradient(180deg,#f0f7f8 0%,#ffffff 100%);">
  <div class="relative">
    <div class="mb-8">
      <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0d9488;">Engagements</p>
      <h3 class="font-display text-2xl sm:text-3xl font-800 text-slate-900">Pick the depth that matches your ops</h3>
    </div>

    <div class="grid lg:grid-cols-3 gap-4">
      @php
        $packages = [
          [
            'name' => 'Starter',
            'price' => '$300–800',
            'tone' => 'border-slate-200',
            'badge' => null,
            'items' => ['Process audit', '2–3 automations', 'Staff training', '30 days of support'],
          ],
          [
            'name' => 'Growth',
            'price' => '$1,000–3,000',
            'tone' => 'border-teal-400',
            'badge' => 'Most chosen',
            'items' => ['Multiple workflows', 'CRM integration', 'Dashboard setup', 'AI chatbot', '90 days of support'],
          ],
          [
            'name' => 'Enterprise',
            'price' => '$5,000+',
            'tone' => 'border-slate-200',
            'badge' => null,
            'items' => ['Company-wide strategy', 'Custom integrations', 'AI assistants', 'Multi-department', 'Ongoing optimization'],
          ],
        ];
      @endphp

      @foreach($packages as $pkg)
        <div class="relative rounded-2xl border {{ $pkg['tone'] }} bg-white p-6 {{ $pkg['badge'] ? 'shadow-sm ring-1 ring-teal-100' : '' }}">
          @if($pkg['badge'])
            <span class="absolute -top-3 left-5 text-[11px] font-semibold px-2.5 py-1 rounded-full text-teal-900" style="background:#99f6e4;">{{ $pkg['badge'] }}</span>
          @endif
          <p class="font-display text-xl font-800 text-slate-900 mb-1">{{ $pkg['name'] }}</p>
          <p class="text-2xl font-800 mb-5" style="color:#0d9488;">{{ $pkg['price'] }}</p>
          <ul class="space-y-2.5">
            @foreach($pkg['items'] as $item)
              <li class="flex items-start gap-2 text-sm text-slate-700">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:#0d9488;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                {{ $item }}
              </li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </div>

    {{-- Ladder SVG under packages --}}
    <svg viewBox="0 0 880 90" class="w-full h-auto mt-8 hidden sm:block" aria-hidden="true">
      <line x1="80" y1="45" x2="800" y2="45" stroke="rgba(13,148,136,0.25)" stroke-width="3"/>
      <circle cx="140" cy="45" r="10" fill="#0d9488"/>
      <circle cx="440" cy="45" r="14" fill="#0d9488"/>
      <circle cx="740" cy="45" r="10" fill="#fb923c"/>
      <text x="140" y="78" text-anchor="middle" fill="#64748b" font-size="12" font-family="Figtree,sans-serif">Prove value</text>
      <text x="440" y="78" text-anchor="middle" fill="#0f172a" font-size="12" font-weight="700" font-family="Figtree,sans-serif">Scale workflows</text>
      <text x="740" y="78" text-anchor="middle" fill="#64748b" font-size="12" font-family="Figtree,sans-serif">Org-wide ops</text>
    </svg>
  </div>
</div>
