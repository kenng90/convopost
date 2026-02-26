<!-- Send Email Section -->
<h5 class="text-muted mt-4">{{ __('Send Email')}}</h5>

<div class="form-group text-center mb-4">

<div class="contacInfo border-radius-lg border p-4 mb-4">
    <div class="mb-3">
        <label class="form-label">{{ __('Recipient Email') }}</label>
        <div class="form-control-plaintext"><strong>@{{ activeChat.email }}</strong></div>
    </div>

    <div class="form-group" v-if="dynamicProperties.emailTemplates && dynamicProperties.emailTemplates.length">
        <label class="form-label">{{ __('Email Template') }}</label>
        <select class="form-control" v-model="dynamicProperties.emailTemplate" @change="onEmailTemplateChange">
            <option selected value="">{{ __('Select a template') }}</option>
            <option  v-for="template in dynamicProperties.emailTemplates" :value="template.subject">@{{ template.name }}</option>
        </select>
    </div>

    <div class="form-group">
        <label class="form-label">{{ __('Subject') }}</label>
        <input 
            type="text" 
            class="form-control" 
            v-model="dynamicProperties.emailSubject" 
            placeholder="{{ __('Enter email subject...') }}"
        >
    </div>

    <div class="form-group">
        <label class="form-label">{{ __('Message') }}</label>
        <div class="d-flex flex-column">
            <div class="d-flex justify-content-end mb-2">
                <button class="btn btn-sm btn-secondary" @click="togglePreview()">
                    <i class="ni" :class="dynamicProperties.showPreview ? 'ni-pencil' : 'ni-eye'"></i>
                    @{{ dynamicProperties.showPreview ? 'Edit' : 'Preview' }}
                </button>
            </div>
            <textarea
                v-if="!dynamicProperties.showPreview"
                class="form-control"
                v-model="dynamicProperties.emailMessage"
                placeholder="{{ __('Type your email message here...') }}"
                rows="6"
            ></textarea>
            <div v-else class="border p-3 rounded bg-white markdown-preview" v-html="marked(dynamicProperties.emailMessage)"></div>
        </div>
    </div>

    <button 
        class="btn btn-primary w-100" 
        @click="sendEmailViaSMTP"
        :disabled="dynamicProperties.isSendingEmail || !dynamicProperties.emailIsSet"
    >
        <i class="ni ni-send mr-2"></i> {{ __('Send Email') }}
    </button>

    <div v-if="!dynamicProperties.emailIsSet" class="mt-3">
        <div class="alert alert-warning">
            <i class="ni ni-alert-circle mr-2"></i> {{ __('No email address set for this contact. Please add an email address before sending.') }}
        </div>
    </div>

    <div v-if="!dynamicProperties.smtpIsSet" class="mt-3">
        <div class="alert alert-warning">
            <i class="ni ni-alert-circle mr-2"></i> {{ __('No SMTP settings found. Please add SMTP settings before sending. This can be done in the Apps section.') }}
        </div>
    </div>

    <div v-if="dynamicProperties.isSendingEmail" class="mt-3 text-center">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">{{ __('Loading...') }}</span>
        </div>
        <p class="mt-2">{{ __('Sending Email...') }}</p>
    </div>

    <div v-if="dynamicProperties.emailError" class="mt-3">
        <div class="alert alert-danger">
            @{{ dynamicProperties.emailError }}
        </div>
    </div>

    <div v-if="dynamicProperties.emailSuccess" class="mt-3">
        <div class="alert alert-success">
            @{{ dynamicProperties.emailSuccess }}
        </div>
    </div>
</div>
</div>

