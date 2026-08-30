{{-- Automation node constellation --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-gray-200 p-6 sm:p-8" style="background:linear-gradient(180deg,#FFF8EC 0%,#ffffff 100%);">
  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-6">
    <div>
      <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#E8A317;">Flowmaker</p>
      <h3 class="font-display text-3xl font-800 text-gray-900">38+ automation nodes</h3>
    </div>
    <p class="text-base text-gray-600 max-w-sm">Triggers, messaging, commerce, bookings, CRM, and AI — one visual canvas.</p>
  </div>

  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
    @foreach([
      ['Triggers', 'Keywords · forms · webhooks'],
      ['Messaging', 'Text · media · templates'],
      ['WA Forms', 'Native multi-screen'],
      ['Commerce', 'Catalog · invoices'],
      ['Payments', 'M-Pesa · Paystack'],
      ['Bookings', 'Slots · events'],
      ['CRM', 'Assign · enroll'],
      ['Journeys', 'Stage moves'],
      ['Conditions', 'Branch · wait'],
      ['HTTP', 'API · webhooks'],
      ['AI nodes', 'Knowledge + handoff'],
      ['Assistant', 'NL → draft flow'],
    ] as [$title, $sub])
      <div class="rounded-2xl border border-gray-200 px-3 py-4 text-center hover:border-[rgba(232,163,23,0.35)] transition-colors" style="background:rgba(232,163,23,0.08);">
        <p class="text-sm font-semibold text-gray-900 mb-1">{{ $title }}</p>
        <p class="text-[11px] text-gray-500 leading-snug">{{ $sub }}</p>
      </div>
    @endforeach
  </div>
</div>
