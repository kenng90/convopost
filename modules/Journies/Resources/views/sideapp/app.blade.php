<div class="contacInfo border-radius-lg border p-4 mb-4">
    <div v-if="dynamicProperties.isLoadingJournies" class="text-center p-3">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">{{ __('Loading...') }}</span>
        </div>
    </div>

    <div v-else-if="!dynamicProperties.journeys || dynamicProperties.journeys.length === 0" class="text-center p-3">
        <p class="text-muted mb-2">{{ __('No journeys created yet.') }}</p>
        <a href="{{ route('journies.index') }}" class="btn btn-outline-primary btn-sm">
            {{ __('Manage journeys') }}
        </a>
    </div>

    <div v-else>
        <div v-for="journey in dynamicProperties.journeys" :key="journey.id" class="mb-3 p-3 border rounded bg-white dark-mode-bg">
            <div class="mb-2 d-flex justify-content-between align-items-start gap-2">
                <div>
                    <i class="ni ni-folder-17 text-primary me-2"></i>
                    <strong>@{{ journey.name }}</strong>
                    <div v-if="journey.in_journey" class="small text-success mt-1">
                        {{ __('Current stage') }}: <strong>@{{ journey.current_stage_name }}</strong>
                    </div>
                    <div v-else class="small text-muted mt-1">{{ __('Not in this journey') }}</div>
                </div>
                <a :href="'/journies/' + journey.id + '/kanban'" class="btn btn-sm btn-outline-primary" title="{{ __('Open kanban') }}">
                    <i class="ni ni-folder-17"></i>
                </a>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-2">
                <button
                    v-for="stage in journey.stages"
                    :key="stage.id"
                    type="button"
                    class="badge badge-md badge-pill mr-1 mb-1 border-0"
                    :class="stage.contact_in ? 'badge-success' : 'badge-primary'"
                    :disabled="stage.contact_in || dynamicProperties.isLoadingJournies"
                    @click="promptMoveContact(journey, stage)"
                >
                    @{{ stage.name }}
                    <span v-if="stage.campaign_name" class="opacity-75"> · @{{ stage.campaign_name }}</span>
                </button>
            </div>

            <button
                v-if="journey.in_journey"
                type="button"
                class="btn btn-sm btn-outline-danger"
                @click="removeFromJourney(journey.id)"
            >
                {{ __('Remove from journey') }}
            </button>
        </div>

        <div v-if="dynamicProperties.journeyActivities && dynamicProperties.journeyActivities.length" class="mt-3">
            <h6 class="text-muted">{{ __('Recent activity') }}</h6>
            <ul class="list-unstyled small mb-0">
                <li v-for="item in dynamicProperties.journeyActivities" :key="item.id" class="mb-2">
                    <strong>@{{ item.journey_name }}</strong>
                    → <span v-if="item.stage_name">@{{ item.stage_name }}</span><span v-else>{{ __('Removed') }}</span>
                    <span class="text-muted">(@{{ item.created_at }})</span>
                </li>
            </ul>
        </div>

        <div class="text-center p-3">
            <a href="{{ route('journies.index') }}" class="btn btn-outline-primary btn-sm">
                {{ __('Manage journeys') }}
            </a>
        </div>
    </div>
</div>
