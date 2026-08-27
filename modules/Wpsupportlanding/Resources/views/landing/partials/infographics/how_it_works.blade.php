{{-- How it works — 4-step architecture like Nominal --}}
<div class="grid lg:grid-cols-4 gap-4">
  @php
    $howSteps = [
      [
        'num' => '01',
        'title' => 'Connect',
        'body' => 'Meta Embedded Signup links WhatsApp, Instagram, and Messenger. Activation OS guides the first test message, contacts, and flow.',
        'points' => ['WhatsApp · Instagram · Messenger', 'Guided path to first reply', '~15 min to go live'],
      ],
      [
        'num' => '02',
        'title' => 'Orchestrate',
        'body' => 'Build journeys, bookings, catalogs, and Flowmaker automations around the same Customer 360 inbox.',
        'points' => ['Journey Pipelines', '38+ automation nodes', 'Forms ↔ Flow Builder bridge'],
      ],
      <!-- [
        'num' => '03',
        'title' => 'Engage',
        'body' => 'Agents reply across Meta channels with Copilot and sidebar commerce tools.',
        'points' => ['Omnichannel team inbox', 'WhatsApp · SMS · email ', 'Catalog  sidebars'],
      ],
      [
        'num' => '04',
        'title' => 'Collect & scale',
        'body' => 'Retry M-Pesa STK when a PIN times out, fall back to Paystack, and chase unpaid invoices on WhatsApp until paid — then resume the flow.',
        'points' => ['M-Pesa STK retry & Paystack fallback', 'Google Calendar sync', 'managed AI credits'],
      ],
    ];
  @endphp
 <!-- [
        'num' => '03',
        'title' => 'Engage',
        'body' => 'Broadcast WhatsApp, SMS & email campaigns. Agents reply across Meta channels with Copilot and sidebar commerce tools.',
        'points' => ['Omnichannel team inbox', 'WhatsApp · SMS · email campaigns', 'Catalog & bookings sidebars'],
      ], -->
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
