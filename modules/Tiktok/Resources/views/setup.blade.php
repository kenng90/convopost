@extends('layouts.app', ['title' => __('TikTok setup')])

@section('content')
<div class="container-fluid mt--7">
    <div class="row">
        <div class="col-xl-8">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('TikTok Business Messaging setup') }}</h3>
                </div>
                <div class="card-body">
                    @include('partials.flash')

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>{{ __('Could not save TikTok connection') }}</strong>
                            <ul class="mb-0 pl-3 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($isConnected)
                        <div class="alert alert-success">{{ __('TikTok is connected for this workspace.') }}</div>
                    @endif

                    <div class="alert alert-warning">
                        <strong>{{ __('Open Beta and regional limits') }}</strong>
                        <p class="mb-2 mt-2">{{ __('TikTok Business Messaging is in Open Beta. Inbound DMs via API are not available for users in the EEA, UK, or Switzerland.') }}</p>
                        <ol class="mb-0 pl-3">
                            <li>{{ __('Paste this webhook URL into TikTok Business Messaging Webhooks (callback URL), or save below to subscribe automatically when TIKTOK_APP_ID and TIKTOK_APP_SECRET are set.') }}</li>
                            <li>{{ __('Replies are limited to 48 hours after the customer’s last message, with a maximum of 10 consecutive outbound messages.') }}</li>
                            <li>{{ __('Access tokens expire in about 24 hours. Provide a refresh token so the hourly tiktok:refresh-tokens job can renew them.') }}</li>
                        </ol>
                    </div>

                    <form method="POST" action="{{ route('tiktok.setup.store') }}">
                        @csrf
                        <input type="hidden" name="webhook_token" value="{{ $verifyToken }}">

                        <div class="form-group">
                            <label>{{ __('Webhook URL') }}</label>
                            <input type="text" class="form-control" readonly value="{{ $webhookUrl }}">
                            <small class="text-muted">{{ __('TikTok POSTs im_receive_msg events to this URL. Return HTTP 200.') }}</small>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Webhook token') }}</label>
                            <input type="text" class="form-control" readonly value="{{ $callbackToken }}">
                        </div>

                        <div class="form-group">
                            <label for="business_id">{{ __('TikTok Business Account ID') }}</label>
                            <input id="business_id" name="business_id" type="text" class="form-control {{ $errors->has('business_id') ? 'is-invalid' : '' }}" required value="{{ old('business_id', $company->getConfig('tiktok_business_id', $connection?->external_account_id ?? '')) }}">
                            @error('business_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="access_token">{{ __('Access token') }}</label>
                            <input id="access_token" name="access_token" type="text" class="form-control {{ $errors->has('access_token') ? 'is-invalid' : '' }}" required value="{{ old('access_token', $company->getConfig('tiktok_access_token', '')) }}">
                            @error('access_token')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="refresh_token">{{ __('Refresh token') }} ({{ __('optional') }})</label>
                            <input id="refresh_token" name="refresh_token" type="text" class="form-control" value="{{ old('refresh_token', '') }}">
                            <small class="text-muted">{{ __('Stored so the hourly refresh job can renew the ~24h access token. Required for unattended send.') }}</small>
                        </div>

                        <button type="submit" class="btn btn-primary">{{ __('Save connection') }}</button>
                        <a href="{{ route('chat.index') }}" class="btn btn-secondary">{{ __('Back to inbox') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
