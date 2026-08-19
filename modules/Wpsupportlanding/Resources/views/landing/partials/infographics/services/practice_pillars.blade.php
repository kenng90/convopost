{{-- Four jobs — how the automation practice engages --}}
@php
  $pillars = [
    [
      'num' => '01',
      'title' => 'Audit',
      'body' => 'We map how work actually moves — chat, phone, CRM, spreadsheets — and score which hours to take back first.',
      'points' => ['Workflow interviews', 'Time & cost model', 'Prioritized backlog'],
    ],
    [
      'num' => '02',
      'title' => 'Automate',
      'body' => 'We wire the 2–3 handoffs that pay for the engagement: approvals, reminders, documents, CRM updates.',
      'points' => ['Approvals & reminders', 'CRM & payments', 'Staff playbooks'],
    ],
    [
      'num' => '03',
      'title' => 'AI workers',
      'body' => 'We stand up workers on your knowledge. They answer FAQs, take calls, and trigger the next step — or a human.',
      'points' => ['Retrieve from your data', 'Chat and voice', 'Actions with limits'],
    ],
    [
      'num' => '04',
      'title' => 'On-network',
      'body' => 'Cloud is the default. When customer data cannot leave the building, the same worker runs on a machine on your LAN.',
      'points' => ['Docs stay inside', 'Local model option', 'Enterprise path'],
    ],
  ];
@endphp

<div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4">
  @foreach($pillars as $pillar)
    <div class="relative rounded-3xl border border-slate-200 bg-white p-6 overflow-hidden group hover:border-teal-300 transition-colors">
      <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full opacity-40 blur-2xl group-hover:opacity-70 transition-opacity" style="background:#99f6e4;"></div>
      <p class="font-display text-4xl font-800 mb-4" style="color:rgba(13,148,136,0.28);">{{ $pillar['num'] }}</p>
      <h3 class="font-display text-xl font-800 text-slate-900 mb-2">{{ $pillar['title'] }}</h3>
      <p class="text-base text-slate-600 leading-relaxed mb-5">{{ $pillar['body'] }}</p>
      <ul class="space-y-2">
        @foreach($pillar['points'] as $point)
          <li class="flex items-start gap-2 text-sm text-slate-700">
            <span class="mt-1.5 w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:#0d9488;"></span>
            {{ $point }}
          </li>
        @endforeach
      </ul>
    </div>
  @endforeach
</div>
