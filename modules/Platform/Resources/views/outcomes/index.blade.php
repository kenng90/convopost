@extends('layouts.app')

@section('admin_title')
    {{ __('Outcomes') }}
@endsection

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
                <div>
                    <h2 class="mb-1">{{ __('Commerce Ops Outcomes') }}</h2>
                    <p class="text-muted mb-0">{{ __('Install Cart Recovery, Booking Convert, and Lead-to-Cash playbooks — journeys + automation in one click.') }}</p>
                </div>
                <form method="POST" action="{{ route('outcomes.install-suite') }}">
                    @csrf
                    <input type="hidden" name="install_flow" value="1">
                    <button class="btn btn-primary" @if($status['suite_installed']) disabled @endif>
                        {{ $status['suite_installed'] ? __('Suite installed') : __('Install Commerce Ops Suite') }}
                    </button>
                </form>
            </div>

            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="text-success">{{ __('Cart recovery') }}</h5>
                            <p class="display-4 mb-0">{{ $metrics['cart_recovery']['recovered'] }}</p>
                            <p class="text-muted mb-0">{{ __(':count abandoned open', ['count' => $metrics['cart_recovery']['abandoned']]) }}</p>
                            <p class="small text-muted mt-2">{{ $metrics['cart_recovery']['recovered_revenue_formatted'] }} {{ __('recovered revenue') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="text-info">{{ __('Booking convert') }}</h5>
                            <p class="display-4 mb-0">{{ $metrics['booking_convert']['attendance_rate'] }}%</p>
                            <p class="text-muted mb-0">{{ __('attendance rate') }}</p>
                            <p class="small text-muted mt-2">{{ __(':count no-shows tracked', ['count' => $metrics['booking_convert']['no_shows']]) }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="text-primary">{{ __('Lead-to-cash') }}</h5>
                            <p class="display-4 mb-0">{{ $metrics['lead_to_cash']['paid'] }}</p>
                            <p class="text-muted mb-0">{{ __('paid in pipeline') }}</p>
                            <p class="small text-muted mt-2">{{ $metrics['lead_to_cash']['pipeline_paid_revenue_formatted'] }} {{ __('paid (30d)') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                @foreach($status['playbooks'] as $key => $playbook)
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="{{ $playbook['icon'] }} mr-2" style="color: {{ $playbook['color'] }}; font-size: 1.4rem;"></i>
                                    <h3 class="mb-0">{{ $playbook['name'] }}</h3>
                                </div>
                                <p class="text-muted">{{ $playbook['tagline'] }}</p>
                                <p class="small flex-grow-1">{{ $playbook['description'] }}</p>

                                @if($playbook['installed'])
                                    <span class="badge badge-success mb-2 align-self-start">{{ __('Installed') }}</span>
                                    <ul class="small text-muted pl-3 mb-3">
                                        <li>{{ __('Journey #:id', ['id' => $playbook['journey_id']]) }}</li>
                                        @if($playbook['flow_id'])
                                            <li>{{ __('Flow #:id', ['id' => $playbook['flow_id']]) }}</li>
                                        @endif
                                        <li>{{ __('~:credits credits / 100 contacts', ['credits' => $playbook['credit_estimate_total']]) }}</li>
                                    </ul>
                                @else
                                    <ul class="small pl-3 mb-3">
                                        @foreach($playbook['checklist'] as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                    <p class="small text-muted">{{ __('Est. ~:credits credits per 100 contacts', ['credits' => $playbook['credit_estimate_total']]) }}</p>
                                @endif

                                <form method="POST" action="{{ route('outcomes.install', $key) }}" class="mt-auto">
                                    @csrf
                                    <input type="hidden" name="install_flow" value="1">
                                    @if($playbook['installed'])
                                        <input type="hidden" name="force" value="1">
                                        <button class="btn btn-sm btn-outline-secondary">{{ __('Reinstall') }}</button>
                                    @else
                                        <button class="btn btn-sm btn-primary">{{ __('Install playbook') }}</button>
                                    @endif
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card shadow-sm mt-2">
                <div class="card-body">
                    <h4 class="mb-2">{{ __('Store commerce webhooks') }}</h4>
                    <p class="text-muted">{{ __('Point Shopify checkout/order webhooks or WooCommerce order webhooks at these URLs (uses your company plain token). Abandoned checkouts enroll Cart Recovery; paid orders mark Recovered.') }}</p>
                    <div class="form-group">
                        <label class="form-control-label">{{ __('Shopify') }}</label>
                        <input class="form-control" readonly value="{{ $webhookUrls['shopify'] }}" onclick="this.select()">
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-control-label">{{ __('WooCommerce') }}</label>
                        <input class="form-control" readonly value="{{ $webhookUrls['woocommerce'] }}" onclick="this.select()">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
