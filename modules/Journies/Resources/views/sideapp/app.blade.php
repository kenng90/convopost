

<!-- Display the name label -->
<div class="form-group">

    <div v-if="dynamicProperties.isLoadingJournies" class="text-center p-3">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">{{ __('Loading...') }}</span>
        </div>
    </div>

    <div v-else-if="dynamicProperties.journies.length == 0 && !dynamicProperties.isLoadingJournies" class="text-center p-3">
        <p class="text-muted mb-2">{{ __('No journies created yet.') }}</p>
        <a href="{{ route('journies.index') }}" class="btn btn-outline-primary btn-sm">
            {{ __('Manage Journies') }}
        </a>
    </div>

    



    <div v-if="dynamicProperties.journies.length > 0">
        <div v-for="journey in dynamicProperties.journies" :key="journey.id" class="mb-3 p-3 border rounded bg-white">
            <div class="mb-2 d-flex justify-content-between align-items-center">
                <div>
                    <i class="ni ni-folder-17 text-primary me-2"></i>
                    <strong>@{{ journey.name }}</strong>
                </div>
                <a :href="'/journies/' + journey.id + '/kanban'" class="btn btn-sm btn-outline-primary" style="cursor: pointer; opacity: 0.8">
                    <i class="ni ni-ruler-pencil"></i>
                </a>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <span v-for="stage in journey.stages" :key="stage.id" :class="{'badge badge-success badge-md badge-pill mr-2 cursor-pointer': stage.contact_in==1, 'badge badge-primary badge-md badge-pill mr-2 cursor-pointer': stage.contact_in!=1}" style="cursor: pointer;" @click="stage.contact_in == 0 ? moveContact(stage.id) : null">
                    @{{ stage.name }}
                </span>
                
            </div>
        </div>
    </div>

    <!-- MANAGE JOURNIES -->
    <div v-if="dynamicProperties.journies.length > 0 && !dynamicProperties.isLoadingJournies" class="text-center p-3">
        <div class="mt-4">
            <a href="{{ route('journies.index') }}" class="btn btn-outline-primary btn-sm">
                {{ __('Manage Journies') }}
            </a>
        </div>
    </div>
</div>


