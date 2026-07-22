@php
    $webhookVerified = $company->getConfig('whatsapp_webhook_verified', 'no') === 'yes';
    $settingsDone = $company->getConfig('whatsapp_settings_done', 'no') === 'yes';
    $hasToken = filled($company->getConfig('whatsapp_permanent_access_token', ''));
    $hasPhoneId = filled($company->getConfig('whatsapp_phone_number_id', ''));
    $hasWabaId = filled($company->getConfig('whatsapp_business_account_id', ''));
    $isConnected = $webhookVerified && $settingsDone;
    $usesEmbeddedSignup = \Akaunting\Module\Facade::has('embeddedlogin')
        && config('embeddedlogin.config_id', '') != '';
@endphp

<div class="card shadow max-height-vh-70 overflow-auto overflow-x-hidden">
    <div class="card-header shadow-lg">
        <b>{{ __('Whatsapp Cloud API - Connection Status') }}</b>
    </div>
    <div class="card-body overflow-auto overflow-x-hidden scrollable-div">
        @if (!$isConnected)
            <div class="alert alert-danger" role="alert">
                <strong>{{ __('Not connected!') }}</strong>
                @if ($usesEmbeddedSignup)
                    {{ __('Start with WhatsApp Setup on the left, finish every Meta screen, then refresh this status.') }}
                @else
                    {{ __('Complete Steps 1–3 on the left in order, then refresh this status.') }}
                @endif
            </div>

            <h5 class="mb-3">{{ __('Checklist') }}</h5>
            <ul class="list-unstyled mb-4">
                @if ($usesEmbeddedSignup)
                    <li class="d-flex align-items-start mb-2">
                        <span class="badge {{ $settingsDone ? 'badge-success' : 'badge-secondary' }} mr-2 mt-1">&nbsp;</span>
                        <span>
                            <strong>{{ __('WhatsApp Setup completed') }}</strong>
                            <div class="text-muted small">
                                {{ $settingsDone
                                    ? __('Signup finished and credentials were saved.')
                                    : __('Click WhatsApp Setup and complete the Facebook / Meta flow.') }}
                            </div>
                        </span>
                    </li>
                @else
                    <li class="d-flex align-items-start mb-2">
                        <span class="badge {{ $webhookVerified ? 'badge-success' : 'badge-secondary' }} mr-2 mt-1">&nbsp;</span>
                        <span>
                            <strong>{{ __('Webhook verified (Step 1)') }}</strong>
                            <div class="text-muted small">
                                {{ $webhookVerified
                                    ? __('Meta successfully reached your webhook.')
                                    : __('Add the Callback URL and Verify token in Meta, then subscribe to Messages.') }}
                            </div>
                        </span>
                    </li>
                    <li class="d-flex align-items-start mb-2">
                        <span class="badge {{ $hasToken ? 'badge-success' : 'badge-secondary' }} mr-2 mt-1">&nbsp;</span>
                        <span>
                            <strong>{{ __('Permanent access token (Step 2)') }}</strong>
                            <div class="text-muted small">
                                {{ $hasToken
                                    ? __('Access token is saved.')
                                    : __('Create a system-user token in Meta and save it here.') }}
                            </div>
                        </span>
                    </li>
                    <li class="d-flex align-items-start mb-2">
                        <span class="badge {{ ($hasPhoneId && $hasWabaId) ? 'badge-success' : 'badge-secondary' }} mr-2 mt-1">&nbsp;</span>
                        <span>
                            <strong>{{ __('Phone number ID & Business Account ID (Step 3)') }}</strong>
                            <div class="text-muted small">
                                {{ ($hasPhoneId && $hasWabaId)
                                    ? __('Account and phone IDs are saved.')
                                    : __('Copy both IDs from Meta WhatsApp → API setup and submit them.') }}
                            </div>
                        </span>
                    </li>
                @endif
                <li class="d-flex align-items-start mb-2">
                    <span class="badge {{ $webhookVerified ? 'badge-success' : 'badge-secondary' }} mr-2 mt-1">&nbsp;</span>
                    <span>
                        <strong>{{ __('Ready to receive messages') }}</strong>
                        <div class="text-muted small">
                            {{ $webhookVerified
                                ? __('Webhook is verified.')
                                : __('Pending until setup finishes successfully.') }}
                        </div>
                    </span>
                </li>
            </ul>

            <button onclick="location.reload()" class="btn btn-outline-success" type="button">{{ __('Refresh status')}}</button>
        @else
            <div class="alert alert-success" role="alert">
                <strong>{{ __('Success!')}}</strong> {{ __('You are now connected to Whatsapp Cloud API. You can start use the system') }}
            </div>
        @endif
    </div>
</div>
