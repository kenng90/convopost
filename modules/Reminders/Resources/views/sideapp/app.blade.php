<div class="contacInfo border-radius-lg border p-4 mb-4">
    <div v-if="dynamicProperties.reservations && dynamicProperties.reservations.length > 0">
        <div v-for="reservation in dynamicProperties.reservations" :key="reservation.id" class="mb-3 p-3 border rounded bg-white    ">
            <div class="mb-2">
                <i class="ni ni-calendar-grid-58 text-primary me-2"></i>
                <strong>{{ __('Reservation') }}</strong>
            </div>

            <div class="mb-3">
                <div class="text-muted mb-1">{{ __('Reference') }}</div>
                <div><strong>#@{{ reservation.external_id }}</strong></div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="text-muted mb-1">{{ __('Start') }}</div>
                    <div>
                        @{{ new Date(reservation.start_date).toLocaleDateString() }}
                        <small class="text-muted">
                            (@{{ new Date(reservation.start_date).toLocaleDateString('en-US', {weekday: 'long'}) }})
                        </small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted mb-1">{{ __('End') }}</div>
                    <div>
                        @{{ new Date(reservation.end_date).toLocaleDateString() }}
                        <small class="text-muted">
                            (@{{ new Date(reservation.end_date).toLocaleDateString('en-US', {weekday: 'long'}) }})
                        </small>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <a :href="'/reminders/reservations/' + reservation.id + '/edit'" class="btn btn-sm btn-primary">
                    <i class="ni ni-bullet-list-67"></i> {{ __('View Details') }}
                </a>
            </div>
        </div>
    </div>
    <div v-else class="text-center text-muted py-3">
        <div>
            <i class="ni ni-single-02 me-2"></i> {{ __('No reservations found for') }} @{{ activeChat.name }}
        </div>
    </div>
</div>
