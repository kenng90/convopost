<script>
    "use strict";

    window.addEventListener('load', function () {
        chatList.addProperty('catalogSidebarCatalogs', []);
        chatList.addProperty('catalogSidebarProducts', []);
        chatList.addProperty('catalogSidebarSearch', '');
        chatList.addProperty('catalogSidebarLoading', false);
        chatList.addProperty('catalog_sidebar_error', '');

        chatList.loadCatalogSidebar = function () {
            chatList.updateProperty('catalogSidebarLoading', true);
            axios.get('/whatsappcatalog/sidebar/catalogs')
                .then(function (response) {
                    if (response.data && response.data.success) {
                        chatList.updateProperty('catalogSidebarCatalogs', response.data.catalogs || []);
                        chatList.updateProperty('catalog_sidebar_error', '');
                    }
                })
                .catch(function () {
                    chatList.updateProperty('catalog_sidebar_error', 'Unable to load catalogs.');
                })
                .finally(function () {
                    chatList.updateProperty('catalogSidebarLoading', false);
                });
        };

        chatList.searchCatalogProducts = function () {
            axios.post('/api/whatsappcatalog/search-products', {
                query: chatList.dynamicProperties.catalogSidebarSearch || ''
            }).then(function (response) {
                if (response.data && response.data.success) {
                    chatList.updateProperty('catalogSidebarProducts', response.data.products || []);
                }
            });
        };

        chatList.sendCatalogLink = function (url) {
            if (typeof chatList.setMessage === 'function') {
                chatList.setMessage(url);
            }
        };

        chatList.sendCatalogProduct = function (row) {
            var item = row.item || {};
            var message = '🛍️ *' + (item.title || 'Product') + '*';
            if (item.price) {
                message += '\n💰 ' + item.price;
            }
            if (item.description) {
                message += '\n' + item.description;
            }
            if (row.shop_url) {
                message += '\n' + row.shop_url;
            }
            if (typeof chatList.setMessage === 'function') {
                chatList.setMessage(message);
            }
        };

        chatList.loadCatalogSidebar();
    });
</script>
