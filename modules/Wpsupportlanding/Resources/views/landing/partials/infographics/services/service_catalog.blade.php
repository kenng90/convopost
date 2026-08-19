{{-- Service catalog map — what we automate --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-slate-200 p-5 sm:p-8" style="background:linear-gradient(180deg,#f0f7f8 0%,#ffffff 100%);">
  <div class="mb-6">
    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0d9488;">Catalog</p>
      <h3 class="font-display text-2xl sm:text-3xl font-800 text-slate-900">Jobs we put on workers — and the workflows around them</h3>
  </div>

  <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
    @php
      $catalog = [
        ['Customer queries', ['FAQs from your docs', 'WhatsApp and web', 'After-hours coverage', 'Answers with sources']],
        ['Calls', ['Answer inbound', 'Take a brief', 'Log it on the contact', 'Handoff phrases']],
        ['Next-step actions', ['Payment link', 'Booking', 'Ticket', 'CRM update']],
        ['Knowledge', ['SOPs and catalogs', 'Past chats', 'Staff lookup', 'Your voice, not generic']],
        ['Approvals', ['Invoices', 'Leave', 'Purchase orders', 'Reminders']],
        ['Follow-ups', ['Lead chase', 'No-shows', 'Renewals', 'Task nudges']],
        ['Human handoff', ['Customer asks for a person', 'Low confidence', 'Brief already written', 'Inbox ready']],
        ['On-network', ['Docs stay on the LAN', 'Local model option', 'Same playbook', 'Enterprise']],
      ];
    @endphp
    @foreach($catalog as [$title, $items])
      <div class="rounded-2xl border border-slate-200 bg-white p-4 hover:border-teal-300 transition-colors">
        <p class="font-display font-800 text-slate-900 mb-3">{{ $title }}</p>
        <ul class="space-y-1.5">
          @foreach($items as $item)
            <li class="text-sm text-slate-600 flex gap-2">
              <span class="text-teal-600">·</span>{{ $item }}
            </li>
          @endforeach
        </ul>
      </div>
    @endforeach
  </div>
</div>
