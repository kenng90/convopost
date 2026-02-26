<script>
    "use strict";

    //Add this function to the Vue instance of chatList
    window.addEventListener('load', function() {

        //Add dynamic properties
        chatList.addProperty('contactWoolistOrders', []);
        chatList.addProperty('woolist_error', '');
        chatList.updateProperty('woolist_error', '');

        //Watch for changes in activeChat
        chatList.$watch('activeChat', function(newVal, oldVal) {
            if(newVal !== oldVal) {
                // Only proceed if email is set and valid
                chatList.updateProperty('contactWoolistOrders', []);
                try{
                    if (newVal.email && newVal.email.length > 5) {
                        axios.post('/api/woolist/getOrders', {
                            contact_id: newVal.id
                        }).then(response => {
                            console.log(response.data);
                            if(response.data && response.data.success) {
                                chatList.updateProperty('contactWoolistOrders', response.data.data);
                                chatList.updateProperty('woolist_error', '');
                            } else {
                                //console.error(response.data.message);
                               //chatList.updateProperty('woolist_error', response.data.message);
                            }
                        });
                    }else{
                        chatList.updateProperty('contactWoolistOrders', []);
                        chatList.updateProperty('woolist_error', 'Please set an email address for this contact so we can fetch their orders');
                    }
                }catch(e){
                }
            }
        });

    });
</script>
