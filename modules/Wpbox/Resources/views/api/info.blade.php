@extends('layouts.app', ['title' => __('Whatsapp API')])
@php
    $docsUrl = \App\Http\Controllers\Api\V1\OpenApiController::documentationUrl();
@endphp
@section('content')
<div class="header pb-8 pt-2 pt-md-7">
    <div class="container-fluid">
        <div class="header-body">
            <h1 class="mb-3 mt--3">🔗 {{__('API Info')}}</h1>
        </div>
    </div>
</div>
<div class="container-fluid mt--8">
    <div class="row">
        <div class="col-12">
            @include('partials.flash')

            <div class="card shadow max-height-vh-70 overflow-auto overflow-x-hidden">
                <div class="card-header shadow-lg">
                    <b>{{ __('API endpoint') }}</b>
                </div>
                <div class="card-body overflow-auto overflow-x-hidden scrollable-div">
                    {{ rtrim(config('app.url'), '/') }}/api/v1
                </div>
            </div>
            <br />
            <div class="card shadow max-height-vh-70 overflow-auto overflow-x-hidden">
                <div class="card-header shadow-lg">
                    <b>{{ __('Your API token') }}</b>
                </div>
                <div class="card-body overflow-auto overflow-x-hidden scrollable-div">
                    {{ $token }}
                </div>
            </div>
            <br />
            <div class="card shadow">
                <div class="card-header shadow-lg">
                    <b>{{ __('Authentication') }}</b>
                </div>
                <div class="card-body">
                    <p>{{ __('Send the token as a Bearer header. Failed auth returns HTTP 401. Use X-Company-Id when the token can access more than one company.') }}</p>
                    <pre class="bg-light p-3 small mb-0">Authorization: Bearer {{ $token }}
X-Company-Id: {{ optional($company)->id }}
Idempotency-Key: unique-write-key
Content-Type: application/json</pre>
                </div>
            </div>
            <br />
            <div class="card shadow">
                <div class="card-header shadow-lg">
                    <b>{{ __('Public API v1') }}</b>
                </div>
                <div class="card-body">
                    <p>{{ __('Plan-aware rate limits apply. WhatsApp broadcasts are capped at 10 messages per second (Cloud API default).') }}</p>
                    <ul class="mb-3">
                        <li><code>GET /api/v1/me</code></li>
                        <li><code>GET|POST /api/v1/contacts</code> — cursor pagination, hard cap 100</li>
                        <li><code>POST /api/v1/messages</code> — WhatsApp (session, template, list, media) and SMS. <code>/api/wpbox/sendmessage</code> remains an alias</li>
                        <li><code>GET|POST /api/v1/conversations</code> — assign, resolve, reply, notes</li>
                        <li><code>POST /api/v1/events</code> — tenant-scoped store events. Replaces <code>POST /webhook/wpbox/store-event</code></li>
                        <li><code>GET|POST /api/v1/webhooks</code> — signed, retried <code>message.*</code>, <code>conversation.*</code>, <code>contact.*</code>, <code>booking.*</code>, <code>payment.*</code></li>
                        <li><code>GET|POST /api/v1/bookings</code> — create/pay with Idempotency-Key</li>
                        <li><code>POST /api/v1/invoices</code> — create and optionally send on WhatsApp</li>
                    </ul>
                    <p class="mb-0">{{ __('Verify webhook signatures with header') }} <code>X-ConvoConnect-Signature: t={timestamp},v1={hmac}</code></p>
                </div>
            </div>
            <br />
            <div class="card shadow max-height-vh-70 overflow-auto overflow-x-hidden">
                <div class="card-header shadow-lg">
                    <b>{{ __('Send API campaign') }}</b>
                </div>
                <div class="card-body">
                    <p class="mb-2">{{ __('Trigger a saved API campaign by ID. Messages are queued unless send_now is true. Ensure the scheduler is running.') }}</p>
                    <code>POST {{ rtrim(config('app.url'), '/') }}/api/wpbox/sendcampaigns</code>
                    <pre class="bg-light p-3 mt-3 mb-0 small">token, campaign_id, phone
data[order][id]=1001   (optional API variable paths)
send_now=true          (optional immediate send)</pre>
                </div>
            </div>

            <br />
            <div class="card shadow max-height-vh-70 overflow-auto overflow-x-hidden">
                <div class="card-footer">
                    <a href="{{ route('wpbox.api.index') }}" class="btn btn-success">🔗 {{ __('List of API campaigns') }}</a>
                    <a href="{{ route('wpbox.api.create') }}" class="btn btn-primary">🔌 {{ __('New API campaign') }}</a>
                    <a href="{{ $docsUrl }}" target="_blank" class="btn btn-outline-primary">🔗 {{ __('Documentation') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
