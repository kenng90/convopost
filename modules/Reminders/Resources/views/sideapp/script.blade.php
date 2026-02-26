<script>
    "use strict";

    //Add this function to the Vue instance of chatList
    window.addEventListener('load', function() {

        //Add dynamic properties
        chatList.addProperty('reservations', []);

        //Watch for changes in activeChat
        chatList.$watch('activeChat', function(newVal, oldVal) {
            if(newVal !== oldVal) {
                console.log("Get user reservations")

                //Axios with post.
                axios.post('/api/reminders/get-contact-reservations', {
                    contact_id: newVal.id,
                    'token': '_'
                }).then(response => {
                    console.log(response.data);
                    chatList.updateProperty('reservations', response.data.reservations);
                }).catch(error => {
                    console.error(error);
                });

            }
        });

    });
</script>
