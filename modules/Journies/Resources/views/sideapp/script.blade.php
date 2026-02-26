<script>
    "use strict";

    //Add this function to the Vue instance of chatList
    window.addEventListener('load', function() {

        //Add dynamic properties
        chatList.addProperty('journies', []);
        chatList.addProperty('isLoadingJournies', false);

        //Watch for changes in activeChat
        function getJourneyData(contactId) {
            chatList.updateProperty('isLoadingJournies', true);
            axios.get('/api/journies/'+contactId).then(function(response) {
                console.log(response.data);
                chatList.updateProperty('journies', response.data);
                chatList.updateProperty('isLoadingJournies', false);
            });
        }

        function moveContact(stageId) {
            console.log('moveContact', stageId);
            if (!chatList.dynamicProperties.isLoadingJournies) {
                chatList.updateProperty('isLoadingJournies', true);
                axios.post('/api/journies/move-contact', {
                    stage_id: stageId,
                    contact_id: chatList.activeChat.id
                }).then(function(response) {
                    console.log(response.data);
                    getJourneyData(chatList.activeChat.id);
                    chatList.updateProperty('isLoadingJournies', false);
                });
            }
        }

       

        chatList.$watch('activeChat', function(newVal, oldVal) {
            if(newVal !== oldVal) {
                console.log('Journey activeChat changed to', newVal);
                getJourneyData(newVal.id);
            }
        });

        //Add the moveContact function to the Vue instance
        chatList.moveContact = moveContact;

    });
</script>
