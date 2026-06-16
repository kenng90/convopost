@hasrole('owner')
<div id="journeyPipelinesReport">
    <div class="row" v-if="!loading">
        <div class="col-xl-4 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <h5 class="text-uppercase text-muted mb-0">{{ __('Journeys') }}</h5>
                    <span class="h2 font-weight-bold mb-0">@{{ stats.journeys_count }}</span>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <h5 class="text-uppercase text-muted mb-0">{{ __('Contacts in pipelines') }}</h5>
                    <span class="h2 font-weight-bold mb-0">@{{ stats.total_contacts }}</span>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <h5 class="text-uppercase text-muted mb-0">{{ __('Campaigns sent') }}</h5>
                    <span class="h2 font-weight-bold mb-0">@{{ (stats.campaign_stats && stats.campaign_stats.sent) || 0 }}</span>
                </div>
            </div>
        </div>
    </div>
    <div v-if="loading" class="text-center py-4">
        <div class="spinner-border text-primary" role="status"></div>
    </div>
</div>
@endhasrole
