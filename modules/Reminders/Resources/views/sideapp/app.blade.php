<div class="contacInfo border-radius-lg border p-4 mb-4">
  <div v-if="dynamicProperties.bookingsLoading" class="text-center text-muted py-3">
    {{ __('Loading bookings...') }}
  </div>

  <template v-else>
    <p class="text-muted small mb-3">{{ __("This contact's bookings") }}</p>

    <div v-if="dynamicProperties.appointments && dynamicProperties.appointments.length > 0" class="mb-4">
      <h5 class="text-muted mb-3">{{ __('Appointments') }}</h5>
      <div
        v-for="appointment in dynamicProperties.appointments"
        :key="'appointment-' + appointment.id"
        class="mb-3 p-3 border rounded bg-white"
      >
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <i class="ni ni-calendar-grid-58 text-primary me-2"></i>
            <strong>{{ __('Appointment') }}</strong>
          </div>
          <span class="badge" :class="appointment.status_badge_class">@{{ appointment.status_label }}</span>
        </div>

        <div class="mb-2" v-if="appointment.service_name">
          <div class="text-muted mb-1">{{ __('Service') }}</div>
          <div>@{{ appointment.service_name }}</div>
        </div>

        <div class="mb-2" v-if="appointment.team_member_name">
          <div class="text-muted mb-1">{{ __('Team member') }}</div>
          <div>@{{ appointment.team_member_name }}</div>
        </div>

        <div class="mb-2" v-if="appointment.external_id">
          <div class="text-muted mb-1">{{ __('Reference') }}</div>
          <div><strong>#@{{ appointment.external_id }}</strong></div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <div class="text-muted mb-1">{{ __('Start') }}</div>
            <div v-if="appointment.start_date">
              @{{ formatBookingDate(appointment.start_date) }}
            </div>
          </div>
          <div class="col-md-6">
            <div class="text-muted mb-1">{{ __('End') }}</div>
            <div v-if="appointment.end_date">
              @{{ formatBookingDate(appointment.end_date) }}
            </div>
          </div>
        </div>

        <div class="text-end">
          <a :href="appointment.show_url" class="btn btn-sm btn-primary">
            <i class="ni ni-bullet-list-67"></i> {{ __('View details') }}
          </a>
        </div>
      </div>
    </div>

    <div v-if="dynamicProperties.eventsEnabled && dynamicProperties.eventRegistrations && dynamicProperties.eventRegistrations.length > 0">
      <h5 class="text-muted mb-3">{{ __('Event registrations') }}</h5>
      <div
        v-for="registration in dynamicProperties.eventRegistrations"
        :key="'registration-' + registration.id"
        class="mb-3 p-3 border rounded bg-white"
      >
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <i class="ni ni-ticket-01 text-primary me-2"></i>
            <strong>{{ __('Event') }}</strong>
          </div>
          <span class="badge" :class="registration.status_badge_class">@{{ registration.status_label }}</span>
        </div>

        <div class="mb-2" v-if="registration.event_title">
          <div class="text-muted mb-1">{{ __('Event') }}</div>
          <div>@{{ registration.event_title }}</div>
        </div>

        <div class="mb-2">
          <div class="text-muted mb-1">{{ __('Party size') }}</div>
          <div>@{{ registration.party_size }}</div>
        </div>

        <div class="mb-2" v-if="registration.external_id">
          <div class="text-muted mb-1">{{ __('Reference') }}</div>
          <div><strong>#@{{ registration.external_id }}</strong></div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <div class="text-muted mb-1">{{ __('Starts') }}</div>
            <div v-if="registration.starts_at">
              @{{ formatBookingDate(registration.starts_at) }}
            </div>
          </div>
          <div class="col-md-6">
            <div class="text-muted mb-1">{{ __('Ends') }}</div>
            <div v-if="registration.ends_at">
              @{{ formatBookingDate(registration.ends_at) }}
            </div>
          </div>
        </div>

        <div class="text-end">
          <a :href="registration.show_url" class="btn btn-sm btn-primary">
            <i class="ni ni-bullet-list-67"></i> {{ __('View details') }}
          </a>
        </div>
      </div>
    </div>

    <div
      v-if="!hasChatBookings()"
      class="text-center text-muted py-3"
    >
      <div>
        <i class="ni ni-single-02 me-2"></i>
        {{ __('No bookings found for') }} @{{ activeChat.name }}
      </div>
    </div>
  </template>
</div>
