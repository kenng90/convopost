@extends('layouts.app', ['title' => __('Meta Cloud API Setup')])
@section('content')
<div class="header pb-8 pt-2 pt-md-7">
    <div class="container-fluid">
        <div class="header-body">
            <h1 class="mb-3 mt--3">💬 {{ __('Meta Cloud API Setup') }}</h1>
            <p class="text-white mt--2 mb-0">{{ __('Use these platform callback URLs in the Meta Developer App. One URL per product; the same verify token for all three.') }}</p>
        </div>
    </div>
</div>
<div class="container-fluid mt--8">
    <div class="row">
        <div class="col-12">
            @include('partials.flash')
        </div>
        <div class="col-lg-12">
            <div class="card shadow">
                <div class="card-header">
                    <b>{{ __('Verify token (all products)') }}</b>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">{{ __('Paste this as the Verify token for WhatsApp, Messenger, and Instagram webhooks.') }}</p>
                    <code style="color:green; font-weight:bold; word-break: break-all;">{{ $token }}</code>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mt-4">
            <div class="card shadow">
                <div class="card-header">
                    <b>{{ __('WhatsApp → Configuration → Webhook') }}</b>
                </div>
                <div class="card-body">
                    <p class="mb-1"><b>{{ __('Callback URL') }}</b></p>
                    <code style="color:green; font-weight:bold; word-break: break-all;">{{ $whatsappWebhookUrl }}</code>
                    <p class="text-muted small mt-3 mb-0">{{ __('Subscribe the messages field on the WhatsApp Business Account object.') }}</p>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mt-4">
            <div class="card shadow">
                <div class="card-header">
                    <b>{{ __('Messenger → Settings → Webhooks') }}</b>
                </div>
                <div class="card-body">
                    <p class="mb-1"><b>{{ __('Callback URL') }}</b></p>
                    <code style="color:green; font-weight:bold; word-break: break-all;">{{ $messengerWebhookUrl }}</code>
                    <p class="text-muted small mt-3 mb-0">{{ __('Do not reuse the WhatsApp /webhook/wpbox URL. Subscribe the messages field, then subscribe each connected Page.') }}</p>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mt-4 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <b>{{ __('Instagram → Webhooks') }}</b>
                </div>
                <div class="card-body">
                    <p class="mb-1"><b>{{ __('Callback URL') }}</b></p>
                    <code style="color:green; font-weight:bold; word-break: break-all;">{{ $instagramWebhookUrl }}</code>
                    <p class="text-muted small mt-3 mb-0">{{ __('Use the Instagram product (or Messenger → Instagram). Subscribe messages. Do not reuse the WhatsApp URL.') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
