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

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>{{ __('Could not save Instagram connection') }}</strong>
                            <ul class="mb-0 pl-3 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($isConnected)
                        <div class="alert alert-success">{{ __('Instagram is connected for this workspace.') }}</div>
                    @endif

                    <div class="alert alert-warning">
                        <strong>{{ __('Webhook verified ≠ messages subscribed') }}</strong>
                        <p class="mb-2 mt-2">{{ __('If DMs never appear and logs show nothing, Meta is not POSTing to this URL. Check all of these:') }}</p>
                        <ol class="mb-0 pl-3">
                            <li>{{ __('Callback URL must be this Instagram webhook (…/webhook/messaging/instagram/receive/…), not WhatsApp.') }}</li>
                            <li>{{ __('Instagram Professional account must be linked to the Facebook Page you connected.') }}</li>
                            <li>{{ __('In Meta app: Messenger (Instagram) or Instagram product → Webhooks → subscribe messages.') }}</li>
                            <li>{{ __('Subscribe the Page / Instagram account to the app webhook (Add subscriptions).') }}</li>
                            <li>{{ __('App Live (or Tester role). Then send a DM from a different Instagram user and watch laravel.log for messaging.webhook.hit.') }}</li>
                        </ol>
                    </div>

                    <div class="alert alert-danger">
                        <strong>{{ __('“No matching user found” / cannot reply') }}</strong>
                        <p class="mb-2 mt-2">{{ __('Meta requires both of these. Saving will validate them:') }}</p>
                        <ol class="mb-0 pl-3">
                            <li>{{ __('Facebook Page must be linked to the Instagram Professional account that receives DMs (Business Suite → Instagram accounts / Page settings).') }}</li>
                            <li>{{ __('Page token / User token must include pages_messaging, instagram_manage_messages, pages_show_list, and pages_read_engagement (or pages_manage_metadata).') }}</li>
                            <li>{{ __('In Graph API Explorer: User token with those scopes → GET /me/accounts?fields=id,name,access_token,instagram_business_account{id,username} → pick the Page whose instagram_business_account.id matches your webhook IG id → paste Page id + token and Save.') }}</li>
                        </ol>
                    </div>

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
                            <input id="page_id" name="page_id" type="text" class="form-control {{ $errors->has('page_id') ? 'is-invalid' : '' }}" required value="{{ old('page_id', $company->getConfig('instagram_page_id', '')) }}">
                            @error('page_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">{{ __('Used when sending replies (Messenger API for Instagram). Must be the Page linked to your Instagram Professional account.') }}</small>
                        </div>

                        <div class="form-group">
                            <label for="instagram_account_id">{{ __('Instagram account ID') }} <span class="text-danger">*</span></label>
                            <input id="instagram_account_id" name="instagram_account_id" type="text" class="form-control {{ $errors->has('instagram_account_id') ? 'is-invalid' : '' }}" required value="{{ old('instagram_account_id', $company->getConfig('instagram_account_id', '')) }}">
                            @error('instagram_account_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">{{ __('Your Instagram Professional account id (for setup/verification). Replies still send via the Facebook Page ID + Page access token with instagram_manage_messages.') }}</small>
                        </div>

                        <div class="form-group">
                            <label for="page_access_token">{{ __('Page access token') }}</label>
                            <input id="page_access_token" name="page_access_token" type="text" class="form-control {{ $errors->has('page_access_token') ? 'is-invalid' : '' }}" required value="{{ old('page_access_token', $company->getConfig('instagram_page_access_token', '')) }}">
                            @error('page_access_token')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">{{ __('Long-lived Page token that includes instagram_manage_messages and pages_messaging.') }}</small>
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
