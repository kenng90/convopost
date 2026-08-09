@extends('layouts.app', ['title' => __('Messenger setup')])

@section('content')
<div class="container-fluid mt--7">
    <div class="row">
        <div class="col-xl-8">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Facebook Messenger setup') }}</h3>
                </div>
                <div class="card-body">
                    @include('partials.flash')

                    @if($isConnected)
                        <div class="alert alert-success">{{ __('Messenger is connected for this workspace.') }}</div>
                    @endif

                    <div class="alert alert-warning">
                        <strong>{{ __('Webhook verified ≠ messages subscribed') }}</strong>
                        <p class="mb-2 mt-2">{{ __('If DMs never appear and logs show nothing, Meta is not POSTing to this URL. Check all of these in Meta Developer Console:') }}</p>
                        <ol class="mb-0 pl-3">
                            <li>{{ __('Use this exact Callback URL (not the WhatsApp /webhook/wpbox/… URL).') }}</li>
                            <li>{{ __('Under Messenger → Settings → Webhooks, subscribe fields: messages (and messaging_postbacks if available).') }}</li>
                            <li>{{ __('Click “Add or remove pages” and subscribe THIS Facebook Page to the webhook.') }}</li>
                            <li>{{ __('App must be Live (or your Facebook user must be a Tester/Admin/Developer role).') }}</li>
                            <li>{{ __('Use Meta’s “Test” / “Send to Me” on the messages field, then watch storage/logs/laravel.log for messaging.webhook.hit.') }}</li>
                        </ol>
                    </div>

                    <form method="POST" action="{{ route('messenger.setup.store') }}">
                        @csrf
                        <input type="hidden" name="webhook_token" value="{{ $verifyToken }}">

                        <div class="form-group">
                            <label>{{ __('Webhook URL') }}</label>
                            <input type="text" class="form-control" readonly value="{{ $webhookUrl }}">
                        </div>

                        <div class="form-group">
                            <label>{{ __('Verify token') }}</label>
                            <input type="text" class="form-control" readonly value="{{ $verifyToken }}">
                        </div>

                        <div class="form-group">
                            <label for="page_id">{{ __('Facebook Page ID') }}</label>
                            <input id="page_id" name="page_id" type="text" class="form-control" required value="{{ $company->getConfig('messenger_page_id', '') }}">
                        </div>

                        <div class="form-group">
                            <label for="page_access_token">{{ __('Page access token') }}</label>
                            <input id="page_access_token" name="page_access_token" type="text" class="form-control" required value="{{ $company->getConfig('messenger_page_access_token', '') }}">
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
