<script>
    "use strict";

    //Load the company SMTP settings
    
    window.addEventListener('load', function() {
       

        


        // Add dynamic properties
        chatList.addProperty('emailTemplates', []);
        chatList.addProperty('emailTemplate', '');
        chatList.addProperty('emailSubject', '');
        chatList.addProperty('emailMessage', '');
        chatList.addProperty('isSendingEmail', false);
        chatList.addProperty('emailError', ''); 
        chatList.addProperty('emailSuccess', '');
        chatList.addProperty('showPreview', false);
        chatList.addProperty('emailIsSet', false);
        chatList.addProperty('smtpIsSet', false);

        var mailHost = '{{ $company->getConfig('MAIL_HOST','') }}';
        console.log(mailHost+"-----");
        console.log(mailHost.length > 3);
        console.log(mailHost.length);
        var smtpIsSet = mailHost.length > 3;
        chatList.updateProperty('smtpIsSet', smtpIsSet);

        //Check if the email is set
        chatList.checkEmail = function() {
            console.log('checkEmail');
            var email = chatList.activeChat.email;
            if(email){
                //OK
                chatList.updateProperty('emailIsSet', true);
            }else{
                //Not OK
                console.log('Not OK');
                console.log(email);
                //Disable the send email button
                chatList.updateProperty('emailIsSet', false);
            }
        };
       

        chatList.togglePreview = function() {
                console.log('togglePreview');
                var showPreview = chatList.getProperty('showPreview');
                var newShowPreview = !showPreview;
                console.log(newShowPreview);
                this.updateProperty('showPreview', newShowPreview);
        };

      

        chatList.onEmailTemplateChange = function() {
            console.log('onEmailTemplateChange');
            console.log(chatList.dynamicProperties.emailTemplate);
            const selectedTemplate = chatList.dynamicProperties.emailTemplates.find(
                template => template.subject === chatList.dynamicProperties.emailTemplate
            );
            
            if (selectedTemplate) {

                //Replace the placeholders with the active chat data
                var message = selectedTemplate.content.replace('{name}', chatList.activeChat.name).replace('{phone}', chatList.activeChat.phone);

                //Replace the subject with the selected template subject
                var subject = selectedTemplate.subject.replace('{name}', chatList.activeChat.name).replace('{phone}', chatList.activeChat.phone);

                //Replace the placeholders with the active chat custom fields
                try {
                    console.log(chatList.activeChatCustomFields);
                    chatList.activeChatCustomFields.forEach(field => {
                        message = message.replace('{' + field.name + '}', field.value || '');
                    });
                } catch (error) {
                    console.error('Error replacing custom fields:', error);
                }

                chatList.updateProperty('emailSubject', subject);
                chatList.updateProperty('emailMessage', message);
            }
        };

        //Load email templates
        chatList.loadEmailTemplates = function() {
            console.log('loadEmailTemplates');

            //Get email templates via axios
            axios.get('/api/emailwpbox/templates').then(response => {
                var templates = JSON.parse(JSON.stringify(response.data));
                console.log(templates);
                chatList.updateProperty('emailTemplates', templates);
            });
        };
        chatList.loadEmailTemplates();

        //Send email
        chatList.sendEmailViaSMTP = function() {
            console.log('sendEmailViaSMTP');
            //Get the email subject and message
            console.log(chatList.dynamicProperties.emailSubject);
            console.log(chatList.dynamicProperties.emailMessage);

            //get the email from the active chat
            var email = chatList.activeChat.email;

            //isSendingEmail
            chatList.updateProperty('isSendingEmail', true);
            //send the email
            axios.post('/api/emailwpbox/send', {
                subject: chatList.dynamicProperties.emailSubject,
                message: chatList.dynamicProperties.emailMessage,
                email: email
            }).then(response => {
                console.log(response);
                if(response.data.success){
                    chatList.updateProperty('emailSuccess', response.data.message);
                }else{
                    chatList.updateProperty('emailError', response.data.error);
                }

                //Reset the email subject and message
                chatList.updateProperty('emailSubject', '');
                chatList.updateProperty('emailMessage', '');
                //Reset the email template
                chatList.updateProperty('emailTemplate', '');

                //isSendingEmail
                chatList.updateProperty('isSendingEmail', false);
            });
        };

        //watch for changes in activeChat
        chatList.$watch('activeChat', function(newVal, oldVal) {
            if(newVal !== oldVal) {
                chatList.checkEmail();
            }
        });

 

       
    
    });
</script>
