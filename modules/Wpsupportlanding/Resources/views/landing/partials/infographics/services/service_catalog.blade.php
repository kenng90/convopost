{{-- Capabilities built around the work the team already does --}}
<div class="infographic-panel relative overflow-hidden rounded-3xl border border-slate-200 p-5 sm:p-8" style="background:linear-gradient(180deg,#f0f7f8 0%,#ffffff 100%);">
  <div class="mb-6">
    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] mb-2" style="color:#0d9488;">Capabilities</p>
    <h3 class="font-display text-2xl sm:text-3xl font-800 text-slate-900">Built around the work your team already does</h3>
  </div>

  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
    @php
      $catalog = [
        ['Agent inbox', 'What changed, what is missing, and what needs review today — from chat, email, and calls.'],
        ['Document intelligence', 'Classify, summarize, route, and search files by meaning, not filename.'],
        ['Follow-up automation', 'Draft follow-ups from open items, meetings, and unanswered chats. You approve, then it sends.'],
        ['Approval workflows', 'AI prepares the action. Your team signs off before records or messages change.'],
        ['Operational memory', 'Tasks, decisions, documents, people, and updates stay connected to the job they belong to.'],
        ['Workflow reports', 'Weekly updates, client briefs, and internal status that should not take a human afternoon.'],
        ['Data entry prep', 'Extract and validate from documents and chats before anything touches CRM or finance.'],
        ['Chat &amp; voice', 'The same context layer answers WhatsApp and inbound calls — and logs a brief for the team.'],
        ['Source-backed search', 'Ask questions and get answers grounded in your documents, chats, and history.'],
      ];
    @endphp
    @foreach($catalog as [$title, $body])
      <div class="rounded-2xl border border-slate-200 bg-white p-5 hover:border-teal-300 transition-colors">
        <p class="font-display font-800 text-slate-900 mb-2">{!! $title !!}</p>
        <p class="text-sm text-slate-600 leading-relaxed">{{ $body }}</p>
      </div>
    @endforeach
  </div>
</div>
