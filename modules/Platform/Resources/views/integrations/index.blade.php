@extends('layouts.app')

@section('admin_title')
    {{ __('Integrations') }}
@endsection

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <h2 class="mb-1">{{ __('Integration hub') }}</h2>
            <p class="text-muted mb-4">{{ __('Connect CRM, accounting, and automation tools to your WhatsApp operations.') }}</p>

            <div class="row">
                @foreach($available as $key => $integration)
                    @php($connected = $connectors->firstWhere('provider', $key))
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body d-flex flex-column">
                                <h4 class="card-title"><i class="{{ $integration['icon'] }}"></i> {{ $integration['name'] }}</h4>
                                <p class="text-muted flex-grow-1">{{ $integration['description'] }}</p>
                                @if($connected)
                                    <span class="badge badge-success mb-2">{{ __('Connected') }}</span>
                                    <form method="POST" action="{{ route('integrations.disconnect', $key) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">{{ __('Disconnect') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('integrations.connect', $key) }}">
                                        @csrf
                                        @if(in_array('api_key', $integration['fields']))
                                            <input type="text" name="api_key" class="form-control form-control-sm mb-2" placeholder="{{ __('API key') }}">
                                        @endif
                                        @if(in_array('webhook_url', $integration['fields']))
                                            <input type="url" name="webhook_url" class="form-control form-control-sm mb-2" placeholder="{{ __('Webhook URL') }}">
                                        @endif
                                        <button class="btn btn-sm btn-primary">{{ __('Connect') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
