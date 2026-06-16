@extends('layouts.app', ['title' => __('Journey analytics')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="mb-0">{{ __('Journey analytics') }}</h1>
            </div>
            <div class="col-auto">
                <a href="{{ route('journies.index') }}" class="btn btn-sm btn-neutral">{{ __('Back to journeys') }}</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('journies.analytics') }}" class="form-inline">
                <label class="mr-2" for="journey_id">{{ __('Journey') }}</label>
                <select name="journey_id" id="journey_id" class="form-control mr-2">
                    <option value="">{{ __('All journeys') }}</option>
                    @foreach($journeys as $journey)
                        <option value="{{ $journey->id }}" @selected($selectedJourneyId == $journey->id)>{{ $journey->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary" type="submit">{{ __('Apply') }}</button>
            </form>
        </div>
    </div>

    <div id="journeyAnalyticsApp">
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div v-else>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card card-stats">
                        <div class="card-body">
                            <h5 class="text-uppercase text-muted mb-0">{{ __('Journeys') }}</h5>
                            <span class="h2">@{{ stats.journeys_count }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-stats">
                        <div class="card-body">
                            <h5 class="text-uppercase text-muted mb-0">{{ __('Contacts in pipelines') }}</h5>
                            <span class="h2">@{{ stats.total_contacts }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-stats">
                        <div class="card-body">
                            <h5 class="text-uppercase text-muted mb-0">{{ __('Campaigns sent') }}</h5>
                            <span class="h2">@{{ (stats.campaign_stats && stats.campaign_stats.sent) || 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header"><h3 class="mb-0">{{ __('Contacts per stage') }}</h3></div>
                <div class="card-body table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Stage') }}</th>
                                <th>{{ __('Contacts') }}</th>
                                <th>{{ __('Campaign') }}</th>
                                <th v-if="conversion.length">{{ __('Conversion from previous') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="stage in stats.stages" :key="stage.id">
                                <td>@{{ stage.name }}</td>
                                <td>@{{ stage.contacts_count }}</td>
                                <td>@{{ stage.campaign_name || '—' }}</td>
                                <td v-if="conversion.length">@{{ conversionRate(stage.id) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header"><h3 class="mb-0">{{ __('Recent activity') }}</h3></div>
                <div class="card-body">
                    <div v-if="!stats.recent_activity.length" class="text-muted">{{ __('No activity yet.') }}</div>
                    <ul class="list-unstyled mb-0" v-else>
                        <li v-for="item in stats.recent_activity" :key="item.id" class="mb-2">
                            <strong>@{{ item.contact_name }}</strong>
                            → @{{ item.stage_name }} (@{{ item.journey_name }})
                            <span class="text-muted">— @{{ item.created_at }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    new Vue({
        el: '#journeyAnalyticsApp',
        data: {
            loading: true,
            stats: { stages: [], recent_activity: [], campaign_stats: {} },
            conversion: [],
        },
        mounted() {
            this.load();
        },
        methods: {
            load() {
                this.loading = true;
                axios.get('{{ route('api.journies.analytics') }}', {
                    params: { journey_id: '{{ $selectedJourneyId }}' || null }
                }).then((response) => {
                    this.stats = response.data.data;
                    this.conversion = response.data.conversion || [];
                    this.loading = false;
                }).catch(() => {
                    this.loading = false;
                });
            },
            conversionRate(stageId) {
                const row = this.conversion.find((item) => item.stage_id === stageId);
                return row && row.conversion_from_previous !== null ? row.conversion_from_previous + '%' : '—';
            }
        }
    });
});
</script>
@endsection
