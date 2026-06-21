<script>
(function () {
    function loadCustomer360(contactId) {
        if (!contactId || !chatList || typeof chatList.updateProperty !== 'function') {
            return;
        }
        chatList.updateProperty('isLoadingCustomer360', true);
        fetch('/api/customer360/' + contactId, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Customer360 request failed with status ' + response.status);
                }

                return response.json();
            })
            .then(data => {
                if (!chatList) {
                    return;
                }
                chatList.updateProperty('customer360', data);
                chatList.updateProperty('isLoadingCustomer360', false);
            })
            .catch(() => {
                if (chatList) {
                    chatList.updateProperty('isLoadingCustomer360', false);
                }
            });
    }

    window.addEventListener('load', function () {
        if (!chatList || typeof chatList.updateProperty !== 'function') {
            return;
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
})();
</script>
