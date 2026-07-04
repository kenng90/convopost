@if ($messagePack)
    <div class="row mb-2">
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm {{ ($messagePack['show_install_prompt'] || $messagePack['show_repair_prompt']) ? 'border-primary' : '' }}">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div class="flex-grow-1">
                            <h4 class="mb-2">{{ __('Booking message templates') }}</h4>
                            @if ($messagePack['installed'])
                                <p class="text-muted mb-2">{{ __('Your booking message pack is complete. Attach campaigns on services or events and set reminder timing.') }}</p>
                                <ul class="list-unstyled small mb-0">
                                    @foreach ($messagePack['templates'] as $item)
                                        <li class="mb-1">
                                            <strong>{{ $item['campaign_name'] }}</strong>
                                            @if ($item['template_status'])
                                                <span class="badge badge-{{ strtoupper($item['template_status']) === 'APPROVED' ? 'success' : 'warning' }}">
                                                    {{ $item['template_status'] }}
                                                </span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif ($messagePack['show_repair_prompt'])
                                <p class="text-muted mb-2">
                                    {{ __('Part of your booking message pack is missing (:present of :total). Repair recreates deleted templates and campaigns without duplicating the rest.', [
                                        'present' => $messagePack['present_count'],
                                        'total' => $messagePack['total_count'],
                                    ]) }}
                                </p>
                                <ul class="list-unstyled small mb-0">
                                    @foreach ($messagePack['templates'] as $item)
                                        <li class="mb-1">
                                            <strong>{{ $item['campaign_name'] }}</strong>
                                            @if ($item['complete'])
                                                <span class="badge badge-success">{{ __('OK') }}</span>
                                            @else
                                                <span class="badge badge-warning">{{ __('Missing') }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif (! $messagePack['whatsapp_ready'])
                                <p class="text-muted mb-0">{{ __('Connect WhatsApp first, then install six ready-made confirmation, reminder, and thank-you templates for appointments and events.') }}</p>
                            @else
                                <p class="text-muted mb-2">{{ __('Install six ready-made WhatsApp templates for booking confirmations, reminders, and thank-you messages. Meta will review them before you can send.') }}</p>
                                <ul class="text-muted small mb-0">
                                    <li>{{ __('Event & appointment booking confirmations') }}</li>
                                    <li>{{ __('Event & appointment reminders') }}</li>
                                    <li>{{ __('Post-visit thank you messages') }}</li>
                                </ul>
                            @endif
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-start">
                            @if ($messagePack['show_install_prompt'])
                                <form method="POST" action="{{ route('reminders.booking-templates.install') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">{{ __('Install booking message pack') }}</button>
                                </form>
                            @elseif ($messagePack['show_repair_prompt'])
                                <form method="POST" action="{{ route('reminders.booking-templates.install') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">{{ __('Repair booking message pack') }}</button>
                                </form>
                            @elseif (! $messagePack['whatsapp_ready'])
                                <a href="{{ route('whatsapp.setup') }}" class="btn btn-outline-primary">{{ __('Connect WhatsApp') }}</a>
                            @endif
                            @if ($messagePack['installed'] || $messagePack['show_repair_prompt'])
                                <a href="{{ $clientNotificationsUrl }}" class="btn btn-sm btn-outline-secondary">{{ __('Client notifications') }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
