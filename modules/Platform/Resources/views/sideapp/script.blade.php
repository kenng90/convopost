<script>
    "use strict";

    window.addEventListener('load', function () {
        if (!chatList || typeof chatList.updateProperty !== 'function') {
            return;
        }

        function loadCustomer360(contactId) {
            if (!contactId || !chatList) {
                return;
            }
            chatList.updateProperty('isLoadingCustomer360', true);
            fetch('/api/customer360/' + contactId, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(r => r.json())
                .then(data => {
                    chatList.updateProperty('customer360', data);
                    chatList.updateProperty('isLoadingCustomer360', false);
                })
                .catch(() => chatList.updateProperty('isLoadingCustomer360', false));
        }

        chatList.updateProperty('isLoadingCustomer360', false);
        chatList.updateProperty('customer360', null);

        chatList.$watch('activeChat', function (chat) {
            if (chat && chat.id) {
                loadCustomer360(chat.id);
            }
        }, { deep: true });

        if (chatList.activeChat && chatList.activeChat.id) {
            loadCustomer360(chatList.activeChat.id);
        }
    });
</script>
