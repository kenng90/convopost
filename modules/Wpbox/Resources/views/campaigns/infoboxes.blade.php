@php
    $isWhatsapp = method_exists($item, 'isWhatsappChannel')
        ? $item->isWhatsappChannel()
        : in_array($item->channel ?? \Modules\Wpbox\Models\Campaign::CHANNEL_WHATSAPP, [null, \Modules\Wpbox\Models\Campaign::CHANNEL_WHATSAPP], true);
    $templateLabel = isset($presenter)
        ? $presenter->templateLabel()
        : ($item->template->name ?? __('Unknown template'));
@endphp

<div class="row mb-5">
    <div class="col-xl-3 col-md-6">
        <div class="card card-stats">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <h5 class="card-title text-uppercase text-muted mb-0">{{ __('Template') }}</h5>
                        <span class="h2 font-weight-bold mb-0">{{ $templateLabel }}</span>
                    </div>
                    <div class="col-auto">
                        <div class="icon icon-shape bg-gradient-info text-white rounded-circle shadow">
                            <i class="ni ni-notification-70"></i>
                        </div>
                    </div>
                </div>
                <p class="mt-3 mb-0 text-sm">
                    @if ($item->timestamp_for_delivery > now())
                        <span class="text mr-2">{{ __('Scheduled for') }}: {{ date($item->timestamp_for_delivery) }}</span>
                    @else
                        <span class="text-v mr-2">{{ $item->timestamp_for_delivery ? $item->timestamp_for_delivery : $item->created_at }}</span>
                    @endif
                </p>
            </div>
        </div>
    </div>

    @if ($item->is_bot)
    @elseif ($item->is_api)
    @else
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">{{ __('Contacts') }}</h5>
                            <span class="h2 font-weight-bold mb-0">{{ $item->send_to }}</span>
                        </div>
                        <div class="col-auto">
                            <div class="icon icon-shape bg-gradient-info text-white rounded-circle shadow">
                                <i class="ni ni-single-02"></i>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-sm">
                        <span class="text mr-2">
                            @if ($total_contacts > 0)
                                {{ round(($item->send_to / $total_contacts) * 100, 2) }}% {{ __('of your contacts') }}
                            @else
                                0% {{ __('of your contacts') }}
                            @endif
                        </span>
                    </p>
                </div>
            </div>
        </div>

        @if ($isWhatsapp)
            <div class="col-xl-3 col-md-6">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">{{ __('Delivered to') }}</h5>
                                @if ($item->send_to > 0)
                                    <span class="h2 font-weight-bold mb-0">{{ round(($item->delivered_to / $item->send_to) * 100, 2) }}%</span>
                                @else
                                    <span class="h2 font-weight-bold mb-0">0%</span>
                                @endif
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-shape bg-gradient-info text-white rounded-circle shadow">
                                    <i class="ni ni-check-bold"></i>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text-success mr-2">{{ $item->delivered_to }}</span>
                            <span class="text-nowrap">{{ __('Contacts') }}</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">{{ __('Read by') }}</h5>
                                @if ($item->delivered_to > 0)
                                    <span class="h2 font-weight-bold mb-0">{{ round(($item->read_by / $item->delivered_to) * 100, 2) }}%</span>
                                @else
                                    <span class="h2 font-weight-bold mb-0">0%</span>
                                @endif
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-shape bg-gradient-green text-white rounded-circle shadow">
                                    <i class="ni ni-chat-round"></i>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text mr-2">
                                {{ $item->read_by }} {{ __('of the') }} {{ $item->delivered_to }} {{ __('Contacts messaged.') }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="col-xl-3 col-md-6">
                <div class="card card-stats">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">{{ __('Sent to') }}</h5>
                                @if ($item->send_to > 0)
                                    <span class="h2 font-weight-bold mb-0">{{ round(($item->sended_to / $item->send_to) * 100, 2) }}%</span>
                                @else
                                    <span class="h2 font-weight-bold mb-0">0%</span>
                                @endif
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-shape bg-gradient-info text-white rounded-circle shadow">
                                    <i class="ni ni-send"></i>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 mb-0 text-sm">
                            <span class="text-success mr-2">{{ $item->sended_to }}</span>
                            <span class="text-nowrap">{{ __('Contacts') }}</span>
                        </p>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
