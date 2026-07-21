{{-- How it works — 4-step architecture like Nominal --}}
<div class="grid lg:grid-cols-4 gap-4">
  @php
    $howSteps = [
      [
        'num' => '01',
        'title' => 'Connect',
        'body' => 'Meta Embedded Signup links your WhatsApp Business number. Activation OS guides the first test message, contacts, and flow.',
        'points' => ['Official Meta Cloud API', 'Guided path to first reply', '~15 min to go live'],
      ],
      [
        'num' => '02',
        'title' => 'Orchestrate',
        'body' => 'Build journeys, bookings, catalogs, and Flowmaker automations around the same Customer 360 inbox.',
        'points' => ['Journey Pipelines', '38+ automation nodes', 'Forms ↔ Flow Builder bridge'],
      ],
      [
        'num' => '03',
        'title' => 'Engage',
        'body' => 'Broadcast across WhatsApp, SMS & email channels. Agents reply with Copilot suggestions and sidebar commerce tools.',
        'points' => ['Shared team inbox', 'Multichannel campaigns', 'Catalog & bookings sidebars'],
      ],
      [
        'num' => '04',
        'title' => 'Collect & scale',
        'body' => 'Close with M-Pesa or Paystack, sync paid status to CRM stages, and extend via developer APIs.',
        'points' => ['M-Pesa STK & Paystack', 'Google Calendar sync', 'managed AI credits'],
      ],
    ];
  @endphp

  @foreach($howSteps as $step)
    <div class="relative rounded-3xl border border-gray-200 p-6 overflow-hidden group" style="background:#ffffff;">
      <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full opacity-20 blur-2xl group-hover:opacity-40 transition-opacity" style="background:#ffffff;"></div>
      <p class="font-display text-4xl font-800 mb-4" style="color:rgba(37,211,102,0.35);">{{ $step['num'] }}</p>
      <h3 class="font-display text-xl font-800 text-gray-900 mb-2">{{ $step['title'] }}</h3>
      <p class="text-base text-gray-600 leading-relaxed mb-5">{{ $step['body'] }}</p>
      <ul class="space-y-2">
        @foreach($step['points'] as $point)
          <li class="flex items-start gap-2 text-sm text-gray-700">
            <span class="mt-1.5 w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:#ffffff;"></span>
            {{ $point }}
          </li>
        @endforeach
      </ul>
    </div>
  @endforeach
</div>
