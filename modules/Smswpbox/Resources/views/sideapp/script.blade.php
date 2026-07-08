@php
    $smsAvailability = app(\App\Services\Telephony\Sms\SmsAvailability::class);
    $smsReady = $smsAvailability->isReady($company);
    $smsStatusMessage = $smsAvailability->statusMessage($company);
@endphp
<script>
    "use strict";

    window.addEventListener('load', function() {

        chatList.addProperty('smsMessage', '');
        chatList.addProperty('isSendingSMS', false);
        chatList.addProperty('smsError', '');
        chatList.addProperty('smsSuccess', '');
        chatList.addProperty('smsTemplates', []);
        chatList.addProperty('smsTemplate', '');
        chatList.addProperty('isSmsSetup', @json($smsReady));
        chatList.addProperty('smsStatusMessage', @json($smsStatusMessage));

        chatList.onSMSTemplateChange = function() {

            var template = chatList.dynamicProperties.smsTemplates.find(template => template.value === chatList.dynamicProperties.smsTemplate);

            var message = template.value.replace('{name}', chatList.activeChat.name).replace('{phone}', chatList.activeChat.phone);

            try {
                chatList.activeChatCustomFields.forEach(field => {
                    message = message.replace('{' + field.name + '}', field.value || '');
                });
            } catch (error) {
                console.error('Error replacing custom fields:', error);
            }

            chatList.updateProperty('smsMessage', message);
        }

        chatList.loadSMSTemplates = function() {
            axios.get('/api/smswpbox/templates').then(response => {
                var templates = JSON.parse(JSON.stringify(response.data));
                chatList.updateProperty('smsTemplates', templates);
            });
        }
        chatList.loadSMSTemplates();

        chatList.sendSMS = function() {
            chatList.updateProperty('isSendingSMS', true);
            chatList.updateProperty('smsError', '');

            axios.post('/api/smswpbox/send', {
                message: chatList.dynamicProperties.smsMessage,
                phone: chatList.activeChat.phone
            }).then(response => {
                chatList.updateProperty('isSendingSMS', false);

                if (response.data.success) {
                    chatList.updateProperty('smsSuccess', response.data.message);
                    chatList.updateProperty('smsMessage', '');

                    setTimeout(() => {
                        chatList.updateProperty('smsSuccess', null);
                    }, 3000);
                } else {
                    chatList.updateProperty('smsError', response.data.message);
                }
            }).catch(() => {
                chatList.updateProperty('isSendingSMS', false);
                chatList.updateProperty('smsError', '{{ __('Failed to send SMS.') }}');
            });
        }

        chatList.$watch('activeChat', function(newVal, oldVal) {
            if(newVal !== oldVal) {
            }
        });

    });
</script>
