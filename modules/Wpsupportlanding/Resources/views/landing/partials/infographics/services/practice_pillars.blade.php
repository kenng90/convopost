{{-- Deployment process: map → connect → first workflow → expand → productize --}}
@php
  $pillars = [
    [
      'num' => '01',
      'title' => 'Map the workflow',
      'body' => 'We talk through the work that wastes the most time and find where AI creates leverage inside how you already operate.',
      'points' => ['Time sinks', 'Handoffs &amp; constraints', 'Where AI should prepare'],
    ],
    [
      'num' => '02',
      'title' => 'Build around your tools',
      'body' => 'We connect email, documents, chat, CRM, and the rest of the stack you already pay for. No rip-and-replace.',
      'points' => ['WhatsApp · voice · email', 'Docs · sheets · CRM', 'Payments · bookings'],
    ],
    [
      'num' => '03',
      'title' => 'Deploy one workflow',
      'body' => 'One high-impact loop goes live fast — with review and approval before anything sends or writes.',
      'points' => ['Clear human gates', 'Source evidence', 'Team using it this week'],
    ],
    [
      'num' => '04',
      'title' => 'Expand, then productize',
      'body' => 'Each deployment teaches the context layer. Repeated workflows become infrastructure that compounds.',
      'points' => ['More workflows', 'Operational memory', 'Patterns become product'],
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
            {!! $point !!}
          </li>
        @endforeach
      </ul>
    </div>
  @endforeach
</div>
