

<!-- Display the name label -->
<h5 class="text-muted mt-4">{{ __('List of products and collections')}}</h5>
<button type="button" class="btn w-100 shadow-none" style="background-color: #538c32; border: none;" @click="openLinkFetcher('shopifylist');">
   
    <span style="color: #ffffff; font-weight: 500; margin-left: 8px;">
        {{ __('Send product') }}
    </span>
    <svg style="width: 24px; fill: #ffffff;" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5.694 12 2.299 3.272c-.236-.607.356-1.188.942-.982l.093.04 18 9a.75.75 0 0 1 .097 1.283l-.097.058-18 9c-.583.291-1.217-.244-1.065-.847l.03-.096L5.694 12 2.299 3.272 5.694 12ZM4.402 4.54l2.61 6.71h6.627a.75.75 0 0 1 .743.648l.007.102a.75.75 0 0 1-.649.743l-.101.007H7.01l-2.609 6.71L19.322 12 4.401 4.54Z" fill="#ffffff" class="fill-212121"></path></svg>
</button>

<!-- Open shop button -->
<a href="https://{{ $company->getConfig('shopify_store_name','') }}.myshopify.com" target="_blank" class="btn w-100 mt-2 shadow-none" style="background-color: #538c32; border: none;">
    <span style="color: #ffffff; font-weight: 500; margin-left: 8px;">
        {{ __('Open shop') }}
    </span>
    <svg style="width: 24px; fill: #ffffff;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 6v2H5v11h11v-5h2v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6zm11-3v8h-2V6.413l-7.793 7.794-1.414-1.414L17.585 5H13V3h8z"/></svg>
</a>


<h5 class="text-muted mt-4">{{ __('Customer orders')}}</h5>

<div v-if="dynamicProperties.shopify_error" class="alert alert-danger">
    @{{ dynamicProperties.shopify_error }}
</div>

<div v-if="dynamicProperties.contactOrders.length > 0">
    <div v-for="order in dynamicProperties.contactOrders" :key="order.id" class="card mb-3 contacInfo border-radius-lg border p-4 mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold">Order #@{{ order.order_number }}</span>
            <span class="text-muted">@{{ new Date(order.created_at).toLocaleDateString() }}</span>
        </div>
        <div class="card-body">
            <div class="row mb-2">
                <div class="col-6">
                    <small class="text-muted">Total:</small>
                    <div class="fw-bold">@{{ order.total_price }} @{{ order.currency }}</div>
                </div>
                <div class="col-6">
                    <small class="text-muted">Status:</small>
                    <div>
                        <span class="badge" :class="{
                            'bg-success': order.financial_status === 'paid',
                            'bg-warning': order.financial_status === 'pending',
                            'bg-danger': order.financial_status === 'refunded'
                        }">
                            @{{ order.financial_status }}
                        </span>
                        <span class="badge ms-1" :class="{
                            'bg-success': order.fulfillment_status === 'fulfilled',
                            'bg-warning': order.fulfillment_status === 'partial',
                            'bg-secondary': !order.fulfillment_status
                        }">
                            @{{ order.fulfillment_status || 'unfulfilled' }}
                        </span>
                    </div>
                </div>
            </div>

            <small class="text-muted">Items:</small>
            <div class="list-group list-group-flush">
                <div v-for="item in order.line_items" :key="item.id" class="list-group-item d-flex justify-content-between align-items-center">
                    <span>@{{ item.name }}</span>
                    <span class="fw-bold">@{{ item.price }} @{{ order.currency }}</span>
                </div>
            </div>

            <div class="mt-2" v-if="order.shipping_address">
                <small class="text-muted">Delivery to:</small>
                <div>@{{ order.shipping_address.country }}</div>
            </div>

            <a :href="'https://admin.shopify.com/store/vbz32s-vz/orders/' + order.id" target="_blank" class="btn btn-sm btn-primary mt-3">
                <span>Open order in Shopify</span>
                <svg style="width: 16px; fill: #ffffff; margin-left: 4px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 6v2H5v11h11v-5h2v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6zm11-3v8h-2V6.413l-7.793 7.794-1.414-1.414L17.585 5H13V3h8z"/></svg>
            </a>
        </div>
    </div>
</div>
