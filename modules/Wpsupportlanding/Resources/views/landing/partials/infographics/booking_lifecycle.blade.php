{{-- Booking flow timeline infographic --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-gray-200 p-5 sm:p-7" style="background:linear-gradient(180deg,#FFF8EC 0%,#ffffff 100%);">
  <div class="mb-5">
    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-1" style="color:#E8A317;">Booking lifecycle</p>
    <p class="text-base text-gray-600">The Bookings app inside ChatDuka — widget to reminder in one loop</p>
  </div>

  <svg viewBox="0 0 680 200" class="w-full h-auto hidden sm:block" role="img" aria-label="Booking flow from service setup through WhatsApp reminders">
    <path d="M40 100 H640" stroke="rgba(232,163,23,0.25)" stroke-width="2" stroke-dasharray="5 7" fill="none"/>
    @php
      $bsteps = [
        [80, 'Services', 'Staff · hours'],
        [220, 'Widget', 'Public page'],
        [360, 'Booked', 'Sidebar + CRM'],
        [500, 'Calendar', 'Google sync'],
        [640, 'Remind', 'WhatsApp'],
      ];
    @endphp
    @foreach($bsteps as $i => [$cx, $t, $s])
      <g>
        <circle cx="{{ $cx }}" cy="100" r="22" fill="#ffffff" stroke="#E8A317" stroke-width="2"/>
        <text x="{{ $cx }}" y="104" text-anchor="middle" fill="#E8A317" font-size="11" font-weight="700" font-family="Fraunces,Georgia,serif">{{ $i + 1 }}</text>
        <text x="{{ $cx }}" y="148" text-anchor="middle" fill="#0f172a" font-size="12" font-weight="700" font-family="Fraunces,Georgia,serif">{{ $t }}</text>
        <text x="{{ $cx }}" y="166" text-anchor="middle" fill="#64748b" font-size="10" font-family="DM Sans,sans-serif">{{ $s }}</text>
      </g>
    @endforeach
  </svg>

  <div class="sm:hidden grid grid-cols-1 gap-2">
    @foreach([
      ['1', 'Services', 'Staff · hours'],
      ['2', 'Widget', 'Public page'],
      ['3', 'Booked', 'Sidebar + CRM'],
      ['4', 'Calendar', 'Google Calendar sync'],
      ['5', 'Remind', 'WhatsApp utility templates'],
    ] as [$n, $t, $s])
      <div class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-2.5" style="background:rgba(232,163,23,0.08);">
        <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold" style="background:linear-gradient(180deg,#FFF8EC 0%,#ffffff 100%);">{{ $n }}</span>
        <div><p class="text-sm font-semibold text-gray-900">{{ $t }}</p><p class="text-xs text-gray-500">{{ $s }}</p></div>
      </div>
    @endforeach
  </div>
</div>
