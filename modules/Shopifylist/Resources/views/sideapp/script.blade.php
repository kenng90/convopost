<script>
    "use strict";

    //Add this function to the Vue instance of chatList
    window.addEventListener('load', function() {

        //Add dynamic properties
        chatList.addProperty('contactOrders', []);
        chatList.addProperty('shopify_error', '');
        chatList.updateProperty('shopify_error', '');

        //Watch for changes in activeChat
        chatList.$watch('activeChat', function(newVal, oldVal) {
            if(newVal !== oldVal) {
                // Only proceed if email is set and valid
                try{
                    if (newVal.email && newVal.email.length > 5) {
                        axios.post('/api/shopifylist/getOrders', {
                            contact_id: newVal.id
                        }).then(response => {
                            console.log(response.data);
                            if(response.data && response.data.success) {
                                chatList.updateProperty('contactOrders', response.data.data);
                                chatList.updateProperty('shopify_error', '');
                            } else {
                                //console.error(response.data.message);
                               //chatList.updateProperty('shopify_error', response.data.message);
                            }
                        });
                    }else{
                        chatList.updateProperty('contactOrders', []);
                        chatList.updateProperty('shopify_error', 'Please set an email address for this contact so we can fetch their orders');
                    }
                }catch(e){
                }
            }
        });

    });
</script>
