<h5 class="text-muted mt-4">{{ __('Product catalogs') }}</h5>

<div v-if="dynamicProperties.catalog_sidebar_error" class="alert alert-danger">
    @{{ dynamicProperties.catalog_sidebar_error }}
</div>

<div v-if="dynamicProperties.catalogSidebarLoading" class="text-muted small mb-2">
    {{ __('Loading catalogs...') }}
</div>

<div v-if="dynamicProperties.catalogSidebarRecentOrders && dynamicProperties.catalogSidebarRecentOrders.length > 0" class="mb-3">
    <h6 class="text-muted">{{ __('Recent orders') }}</h6>
    <div v-for="order in dynamicProperties.catalogSidebarRecentOrders" :key="'order-' + order.id" class="card mb-2 border p-3">
        <div class="fw-bold">@{{ order.order_number }}</div>
        <small class="text-muted">
            @{{ order.customer_name || order.customer_phone || '—' }} · @{{ order.status }}
        </small>
        <div class="mt-1 small">@{{ order.currency }} @{{ order.total_amount }} · @{{ order.item_count }} {{ __('items') }}</div>
        <button
            type="button"
            class="btn btn-sm btn-outline-secondary mt-2 w-100"
            v-if="order.customer_phone"
            @click="sendOrderSummary(order)"
        >
            {{ __('Mention order in chat') }}
        </button>
    </div>
</div>

<div v-if="dynamicProperties.catalogSidebarCatalogs.length > 0" class="mb-3">
    <div v-for="catalog in dynamicProperties.catalogSidebarCatalogs" :key="catalog.id" class="card mb-2 border p-3">
        <div class="fw-bold">@{{ catalog.name }}</div>
        <small class="text-muted">@{{ catalog.item_count }} {{ __('products') }} <span v-if="catalog.catalog_mode">(@{{ catalog.catalog_mode }})</span></small>
        <button type="button" class="btn btn-sm btn-primary mt-2 w-100" @click="sendCatalogLink(catalog.public_url)">
            {{ __('Send shop link') }}
        </button>
    </div>
</div>

<div v-if="dynamicProperties.catalogSidebarStoreProducts && dynamicProperties.catalogSidebarStoreProducts.length > 0" class="mb-3">
    <h6 class="text-muted">
        {{ __('Store products') }}
        <span v-if="dynamicProperties.catalogSidebarStoreSource" class="text-capitalize">(@{{ dynamicProperties.catalogSidebarStoreSource }})</span>
    </h6>
    <div v-for="(product, index) in dynamicProperties.catalogSidebarStoreProducts" :key="'store-' + index" class="card mb-2 border p-3">
        <div class="fw-bold">@{{ product.title }}</div>
        <small class="text-muted" v-if="product.description">@{{ product.description }}</small>
        <button type="button" class="btn btn-sm btn-outline-primary mt-2 w-100" @click="sendStoreProductLink(product)">
            {{ __('Send link') }}
        </button>
    </div>
</div>

<h5 class="text-muted mt-4">{{ __('Search products') }}</h5>
<input type="text" class="form-control mb-2" v-model="dynamicProperties.catalogSidebarSearch" @keyup.enter="searchCatalogProducts" placeholder="{{ __('Search by name or category') }}">
<button type="button" class="btn w-100 mb-3" style="background-color: #8966FE; color: #fff;" @click="searchCatalogProducts">
    {{ __('Search') }}
</button>

<div v-if="dynamicProperties.catalogSidebarProducts.length > 0">
    <div v-for="(row, index) in dynamicProperties.catalogSidebarProducts" :key="index" class="card mb-2 border p-3">
        <div class="fw-bold">@{{ row.item.title }}</div>
        <small class="text-muted">@{{ row.catalog_name }}</small>
        <div v-if="row.item.price" class="mt-1">@{{ row.item.price }}</div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-2 w-100" @click="sendCatalogProduct(row)">
            {{ __('Send product') }}
        </button>
    </div>
</div>

<a href="{{ route('catalogs.page') }}" target="_blank" class="btn w-100 mt-2 shadow-none" style="background-color: #8966FE; border: none;">
    <span style="color: #ffffff; font-weight: 500;">{{ __('Manage catalogs') }}</span>
</a>
