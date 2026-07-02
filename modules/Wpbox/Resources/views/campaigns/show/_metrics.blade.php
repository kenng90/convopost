<div class="row mb-5">
    <div class="col-xl-3 col-md-6">
        <div class="card card-stats">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <h5 class="card-title text-uppercase text-muted mb-0">
                            @if ($presenter->channel() === \Modules\Wpbox\Models\Campaign::CHANNEL_WHATSAPP)
                                {{ __('Template') }}
                            @else
                                {{ __('Content') }}
                            @endif
                        </h5>
                        <span class="h2 font-weight-bold mb-0">{{ $presenter->templateLabel() }}</span>
                    </div>
                    <div class="col-auto">
                        <div class="icon icon-shape bg-gradient-info text-white rounded-circle shadow">
                            <i class="ni ni-notification-70"></i>
                        </div>
                    </div>
                </div>
                <p class="mt-3 mb-0 text-sm">
                    <span class="text mr-2">{{ $presenter->channelLabel() }}</span>
                </p>
            </div>
        </div>
    </div>

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

    @if ($analytics['show_delivered_metric'] ?? false)
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">{{ __('Delivered to') }}</h5>
                            @if ($item->send_to > 0)
                                <span class="h2 font-weight-bold mb-0">{{ $analytics['delivery_rate'] }}%</span>
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
                                <span class="h2 font-weight-bold mb-0">{{ $analytics['read_rate'] }}%</span>
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
                            <h5 class="card-title text-uppercase text-muted mb-0">{{ __('Sent') }}</h5>
                            <span class="h2 font-weight-bold mb-0">{{ $analytics['sent_count'] }}</span>
                        </div>
                        <div class="col-auto">
                            <div class="icon icon-shape bg-gradient-info text-white rounded-circle shadow">
                                <i class="ni ni-send"></i>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-sm">
                        <span class="text mr-2">{{ $analytics['success_rate'] }}% {{ __('success rate') }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">{{ __('Failed') }}</h5>
                            <span class="h2 font-weight-bold mb-0">{{ $analytics['failed_count'] }}</span>
                        </div>
                        <div class="col-auto">
                            <div class="icon icon-shape bg-gradient-red text-white rounded-circle shadow">
                                <i class="ni ni-fat-remove"></i>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-sm">
                        <span class="text mr-2">{{ $analytics['failure_rate'] }}% {{ __('failure rate') }}</span>
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
