<div class="row mb-4 mt--3">
    <div class="col-md-12">
        <div class="card bg-secondary shadow">
            <div class="card-header border-0">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h3 class="mb-0">{{ __('Your current plan') }}</h3>
                    </div>

                </div>
            </div>
            <div class="card-body">
                <p>{{ __('You are currently using the ').$planAttribute['plan']['name']." ".__('plan') }}<p>

                <!-- ORDERS -->
                <div class="alert alert-{{$planAttribute['ordersAlertType']}}" role="alert">
                    {{ $planAttribute['ordersMessage'] }}
                </div>

                <!-- ITEMS -->
                <div class="alert alert-{{$planAttribute['itemsAlertType']}}" role="alert">
                    {{ $planAttribute['itemsMessage'] }}
                </div>

                @if (!empty($planAttribute['catalogItemsMessage']))
                <div class="alert alert-{{ $planAttribute['catalogItemsAlertType'] ?? 'info' }}" role="alert">
                    {{ $planAttribute['catalogItemsMessage'] }}
                </div>
                @endif

                @if (!empty($planAttribute['usageSummary']))
                <div class="mt-3">
                    <h4 class="mb-3">{{ __('Plan usage this period') }}</h4>
                    @foreach ($planAttribute['usageSummary'] as $usage)
                    <div class="alert alert-{{ $usage['alert'] }}" role="alert">
                        {{ $usage['label'] }}:
                        <strong>{{ number_format($usage['used']) }}</strong>
                        @if ($usage['unlimited'])
                            / {{ __('Unlimited') }}
                        @else
                            / {{ number_format($usage['limit']) }}
                            @if (! is_null($usage['remaining']))
                                ({{ __(':count remaining', ['count' => number_format($usage['remaining'])]) }})
                            @endif
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif

                @if (!empty($planAttribute['seatBillingSummary']))
                <div class="mt-3">
                    <h4 class="mb-3">{{ __('Billable add-ons') }}</h4>
                    @foreach ($planAttribute['seatBillingSummary'] as $seat)
                    <div class="alert alert-info" role="alert">
                        {{ $seat['label'] }}:
                        <strong>{{ number_format($seat['used']) }}</strong>
                        {{ __('used') }}
                        ({{ __(':count included', ['count' => number_format($seat['included'])]) }})
                        @if ($seat['billable'] > 0)
                            — <strong>{{ number_format($seat['billable']) }}</strong> {{ __('billed on Stripe') }}
                            @if ($seat['unit_price'] > 0)
                                @ {{ money($seat['unit_price'], config('settings.cashier_currency'), config('settings.do_convertion', true)) }}/{{ __('mo') }}
                            @endif
                        @else
                            — {{ __('No add-on seats') }}
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif

                
                    


                @if(strlen(auth()->user()->plan_status)>0)
                <p>{{ __('Status').": "}} <strong>{{ __(auth()->user()->plan_status) }}</strong><p>
                @endif
            </div>

            @if(!$showLinkToPlans)
                @if(strlen(auth()->user()->cancel_url)>5 && ( config('settings.subscription_processor') == "Stripe"))
                    <div class="card-footer py-4">
                        🔒 {{ __('Subscriptions are managed by Stripe securely.') }}

                        <br />
                        <a href="{{ route('billing') }}" class="btn btn-sm btn-outline-danger">{{__('Manage subscription')}}</a>
                    </div>
                @endif

                @if (!(config('settings.subscription_processor') == "Stripe" || config('settings.subscription_processor') == "Local"))
                    <!-- Payment processor actions -->
                    @include($subscription_processor.'-subscribe::actions')
                @endif
            @else
                <div class="card-footer py-4 allign-right right">
                    <a href="{{ route('plans.current') }}" class="btn btn-success">{{__('Go to plans')}}</a>
                </div>
            @endif

            
        </div>

    </div>

</div>