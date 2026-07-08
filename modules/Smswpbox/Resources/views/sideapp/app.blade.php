@php
    $smsAvailability = app(\App\Services\Telephony\Sms\SmsAvailability::class);
    $smsReady = $smsAvailability->isReady($company);
    $smsStatusMessage = $smsAvailability->statusMessage($company);
    $smsUsesPlatform = $smsAvailability->resolveProvider($company) === \App\Services\Telephony\Sms\SmsProvider::HOSTPINNACLE;
@endphp
<!-- Send SMS Section -->
<h5 class="text-muted mt-4">{{ __('Send SMS')}}</h5>
<div class="contacInfo border-radius-lg border p-4 mb-4">
    @if($smsUsesPlatform)
        <div class="alert alert-info mb-3">
            {{ __('SMS is provided by ConvoConnect with your organization Sender ID.') }}
        </div>
    @endif

    <div class="mb-3">
        <label class="form-label">{{ __('Recipient Phone Number') }}</label>
        <div class="form-control-plaintext"><strong>@{{ activeChat.phone }}</strong></div>
    </div>

    <div class="form-group" v-if="dynamicProperties.smsTemplates && dynamicProperties.smsTemplates.length">
        <label class="form-label">{{ __('SMS Template') }}</label>
        <select class="form-control" v-model="dynamicProperties.smsTemplate" @change="onSMSTemplateChange">
            <option value="">{{ __('Select a template') }}</option>
            <option v-for="template in dynamicProperties.smsTemplates" :value="template.value">@{{ template.name }}</option>
        </select>
    </div>

    <div class="form-group">
        <textarea 
            class="form-control" 
            v-model="dynamicProperties.smsMessage" 
            rows="4" 
            placeholder="{{ __('Type your SMS message here...') }}"
        ></textarea>
    </div>

    <button 
        class="btn btn-primary w-100" 
        @click="sendSMS"
        :disabled="dynamicProperties.isSendingSMS || !dynamicProperties.isSmsSetup"
    >
        <i class="ni ni-send mr-2"></i> {{ __('Send SMS') }}
    </button>

    <div v-if="dynamicProperties.isSendingSMS" class="mt-3 text-center">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">{{ __('Loading...') }}</span>
        </div>
        <p class="mt-2">{{ __('Sending SMS...') }}</p>
    </div>

    <div v-if="dynamicProperties.smsError" class="mt-3">
        <div class="alert alert-danger">
            @{{ dynamicProperties.smsError }}
        </div>
    </div>

    <div v-if="!dynamicProperties.isSmsSetup" class="mt-3">
        <div class="alert alert-warning">
            @{{ dynamicProperties.smsStatusMessage }}
        </div>
    </div>

    <div v-if="dynamicProperties.smsSuccess" class="mt-3">
        <div class="alert alert-success">
            @{{ dynamicProperties.smsSuccess }}
        </div>
    </div>
</div>

