<script>
    "use strict";

    //Add this function to the Vue instance of chatList
    window.addEventListener('load', function() {

        //Add dynamic properties
        chatList.addProperty('someProperty', 'someValue');

        //Add dynamic properties
        chatList.addProperty('smsMessage', '');
        chatList.addProperty('isSendingSMS', false);
        chatList.addProperty('smsError', '');
        chatList.addProperty('smsSuccess', '');
        chatList.addProperty('smsTemplates', []);
        chatList.addProperty('smsTemplate', '');
        chatList.addProperty('isTwillioSetup', false);

        //Check if Twilio is setup by checking if TWILIO_ACCOUNT_SID exists and has length > 3
        var twilioAccountSid = '{{ $company->getConfig('TWILIO_ACCOUNT_SID','') }}';
        console.log(twilioAccountSid+"-----");
        console.log(twilioAccountSid.length > 3);
        console.log(twilioAccountSid.length);
        var isTwillioSetup = twilioAccountSid.length > 3;
        chatList.updateProperty('isTwillioSetup', isTwillioSetup);


        
        chatList.onSMSTemplateChange = function() {

            //Get the template
            var template = chatList.dynamicProperties.smsTemplates.find(template => template.value === chatList.dynamicProperties.smsTemplate);
            
            //Replace the placeholders with the active chat data
            var message = template.value.replace('{name}', chatList.activeChat.name).replace('{phone}', chatList.activeChat.phone);

            //Replace the placeholders with the active chat custom fields
            try {
                console.log(chatList.activeChatCustomFields);
                chatList.activeChatCustomFields.forEach(field => {
                    message = message.replace('{' + field.name + '}', field.value || '');
                });
            } catch (error) {
                console.error('Error replacing custom fields:', error);
            }

            chatList.updateProperty('smsMessage', message);
        }

        //Load SMS templates, make an API call to get the templates
        chatList.loadSMSTemplates = function() {
            axios.get('/api/smswpbox/templates').then(response => {
                var templates = JSON.parse(JSON.stringify(response.data));
                console.log(templates);
                chatList.updateProperty('smsTemplates', templates);

                //log it
                console.log('smsTemplates');
                console.log(chatList.dynamicProperties.smsTemplates);
            });
        }
        chatList.loadSMSTemplates();


        //Send SMS
        chatList.sendSMS = function() {
            chatList.updateProperty('isSendingSMS', true);

            //Call API to send SMS
            axios.post('/api/smswpbox/send', {
                message: chatList.dynamicProperties.smsMessage,
                phone: chatList.activeChat.phone
            }).then(response => {
                if (response.data.success) {
                    chatList.updateProperty('isSendingSMS', false);
                    chatList.updateProperty('smsSuccess', response.data.message);

                    //Clear the SMS message
                    chatList.updateProperty('smsMessage', '');

                    //After 3 seconds, clear the success message
                    setTimeout(() => {
                        chatList.updateProperty('smsSuccess', null);
                    }, 3000);
                } else {
                    chatList.updateProperty('smsError', response.data.message);
                    chatList.updateProperty('isSendingSMS', false);

                    
                }
            });
        }

        //Watch for changes in activeChat
        chatList.$watch('activeChat', function(newVal, oldVal) {
            if(newVal !== oldVal) {
            }
        });

    });
</script>
