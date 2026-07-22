{{-- Service catalog map — what we automate --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-slate-200 p-5 sm:p-8" style="background:linear-gradient(180deg,#f0f7f8 0%,#ffffff 100%);">
  <div class="mb-6">
    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0d9488;">Catalog</p>
    <h3 class="font-display text-2xl sm:text-3xl font-800 text-slate-900">Any process your business can automate</h3>
  </div>

  <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
    @php
      $catalog = [
        ['CRM Automation', ['Capture leads', 'Assign owners', 'Follow-up emails', 'Pipeline reminders']],
        ['AI Support', ['Website chatbots', 'FAQ automation', 'Ticket routing', 'Appointment booking']],
        ['Marketing', ['Email sequences', 'Welcome campaigns', 'Lead nurturing', 'Newsletter runs']],
        ['Sales', ['Proposals', 'Contracts', 'Quotes', 'Pipeline automation']],
        ['Internal Ops', ['HR onboarding', 'Timesheets', 'Expenses', 'Procurement']],
        ['Reporting', ['Weekly reports', 'Executive dashboards', 'KPI tracking', 'Financial summaries']],
        ['Documents', ['Auto-generation', 'Approvals', 'Reminders', 'Templates']],
        ['AI Solutions', ['Assistants', 'Summaries', 'Meeting notes', 'Email drafting']],
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
