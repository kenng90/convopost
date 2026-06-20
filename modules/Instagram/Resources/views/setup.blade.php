@extends('layouts.app', ['title' => __('Instagram Direct setup')])

@section('content')
<div class="container-fluid mt--7">
    <div class="row">
        <div class="col-xl-8">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Instagram Direct setup') }}</h3>
                </div>
                <div class="card-body">
                    @include('partials.flash')

                    @if($isConnected)
                        <div class="alert alert-success">{{ __('Instagram is connected for this workspace.') }}</div>
                    @endif

                    <form method="POST" action="{{ route('instagram.setup.store') }}">
                        @csrf
                        <input type="hidden" name="webhook_token" value="{{ $verifyToken }}">

                        <div class="form-group">
                            <label>{{ __('Webhook URL') }}</label>
                            <input type="text" class="form-control" readonly value="{{ $webhookUrl }}">
                            <small class="text-muted">{{ __('Subscribe your Facebook Page to messages in Meta Developer Console.') }}</small>
                        </div>

                        <div class="form-group">
                            <label>{{ __('Verify token') }}</label>
                            <input type="text" class="form-control" readonly value="{{ $verifyToken }}">
                        </div>

                        <div class="form-group">
                            <label for="page_id">{{ __('Facebook Page ID') }}</label>
                            <input id="page_id" name="page_id" type="text" class="form-control" required value="{{ $company->getConfig('instagram_page_id', '') }}">
                        </div>

                        <div class="form-group">
                            <label for="instagram_account_id">{{ __('Instagram account ID') }}</label>
                            <input id="instagram_account_id" name="instagram_account_id" type="text" class="form-control" value="{{ $company->getConfig('instagram_account_id', '') }}">
                        </div>

                        <div class="form-group">
                            <label for="page_access_token">{{ __('Page access token') }}</label>
                            <input id="page_access_token" name="page_access_token" type="text" class="form-control" required value="{{ $company->getConfig('instagram_page_access_token', '') }}">
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
