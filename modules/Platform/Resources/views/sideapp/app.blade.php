<div class="contacInfo border-radius-lg border p-4 mb-4" v-if="activeChat && activeChat.id">
    <div v-if="dynamicProperties.isLoadingCustomer360" class="text-center p-3">
        <div class="spinner-border text-primary" role="status"></div>
    </div>
    <div v-else-if="dynamicProperties.customer360">
        <h5 class="mb-3"><i class="ni ni-single-02 text-info"></i> {{ __('Customer 360') }}</h5>

        <div class="mb-3" v-if="dynamicProperties.customer360.outcomes && dynamicProperties.customer360.outcomes.playbooks">
            <h6 class="text-uppercase text-muted mb-2">{{ __('Outcomes') }}</h6>
            <div v-if="dynamicProperties.customer360.outcomes.open_abandoned_carts" class="small mb-2 text-warning">
                {{ __('Open abandoned carts') }}: @{{ dynamicProperties.customer360.outcomes.open_abandoned_carts }}
            </div>
            <div v-for="(p, key) in dynamicProperties.customer360.outcomes.playbooks" :key="key" class="small mb-1">
                <strong>@{{ p.name }}</strong>
                <span v-if="p.in_playbook"> — @{{ p.current_stage }}</span>
                <span v-else class="text-muted"> — {{ __('not in pipeline') }}</span>
            </div>
        </div>

        <div class="mb-3" v-if="dynamicProperties.customer360.journeys && dynamicProperties.customer360.journeys.length">
            <h6 class="text-muted text-uppercase small">{{ __('Journey') }}</h6>
            <div v-for="j in dynamicProperties.customer360.journeys" :key="j.id" class="small mb-1">
                <strong>@{{ j.name }}</strong>:
                <span v-if="j.in_journey" class="text-success">@{{ j.current_stage }}</span>
                <span v-else class="text-muted">{{ __('Not enrolled') }}</span>
            </div>
        </div>

        <div class="mb-3" v-if="dynamicProperties.customer360.bookings && dynamicProperties.customer360.bookings.length">
            <h6 class="text-muted text-uppercase small">{{ __('Bookings') }}</h6>
            <div v-for="b in dynamicProperties.customer360.bookings" :key="b.id" class="small mb-1">
                @{{ b.service_name }} — @{{ b.start_time }} (@{{ b.status }})
            </div>
        </div>

        <div class="mb-3" v-if="dynamicProperties.customer360.orders && dynamicProperties.customer360.orders.length">
            <h6 class="text-muted text-uppercase small">{{ __('Orders & invoices') }}</h6>
            <div v-for="o in dynamicProperties.customer360.orders" :key="o.invoice_number" class="small mb-1">
                @{{ o.invoice_number }} — @{{ o.currency }} @{{ o.amount }}
                <span class="badge badge-pill" :class="o.status === 'paid' ? 'badge-success' : 'badge-warning'">@{{ o.status }}</span>
            </div>
        </div>

        <div class="mb-3" v-if="dynamicProperties.customer360.campaigns && dynamicProperties.customer360.campaigns.length">
            <h6 class="text-muted text-uppercase small">{{ __('Campaign history') }}</h6>
            <div v-for="c in dynamicProperties.customer360.campaigns" :key="c.id" class="small mb-1">
                @{{ c.name }} <span class="text-muted">(@{{ c.created_at }})</span>
            </div>
        </div>

        <div class="mb-0">
            <h6 class="text-muted text-uppercase small">{{ __('Conversation summary') }}</h6>
            <pre class="small bg-light p-2 rounded mb-0" style="white-space: pre-wrap;">@{{ dynamicProperties.customer360.conversation_summary }}</pre>
        </div>
    </div>
</div>
