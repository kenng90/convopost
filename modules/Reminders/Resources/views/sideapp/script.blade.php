<script>
    "use strict";

    window.addEventListener('load', function () {
        chatList.addProperty('appointments', []);
        chatList.addProperty('eventRegistrations', []);
        chatList.addProperty('eventsEnabled', false);
        chatList.addProperty('bookingsLoading', false);

        chatList.formatBookingDate = function (isoDate) {
            if (!isoDate) {
                return '';
            }

            const date = new Date(isoDate);

            return date.toLocaleString(undefined, {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        };

        chatList.hasChatBookings = function () {
            const appointments = this.dynamicProperties.appointments || [];
            const registrations = this.dynamicProperties.eventRegistrations || [];

            return appointments.length > 0 || registrations.length > 0;
        };

        chatList.loadContactBookings = function (contactId) {
            if (!contactId) {
                chatList.updateProperty('appointments', []);
                chatList.updateProperty('eventRegistrations', []);
                chatList.updateProperty('eventsEnabled', false);
                chatList.updateProperty('bookingsLoading', false);

                return;
            }

            chatList.updateProperty('bookingsLoading', true);

            axios.post('/api/reminders/get-contact-bookings', {
                contact_id: contactId,
                token: '_',
            }).then(function (response) {
                chatList.updateProperty('appointments', response.data.appointments || []);
                chatList.updateProperty('eventRegistrations', response.data.event_registrations || []);
                chatList.updateProperty('eventsEnabled', !!response.data.events_enabled);
                chatList.updateProperty('bookingsLoading', false);
            }).catch(function (error) {
                console.error(error);
                chatList.updateProperty('appointments', []);
                chatList.updateProperty('eventRegistrations', []);
                chatList.updateProperty('eventsEnabled', false);
                chatList.updateProperty('bookingsLoading', false);
            });
        };

        chatList.$watch('activeChat', function (newVal, oldVal) {
            if (newVal !== oldVal) {
                chatList.loadContactBookings(newVal?.id);
            }
        });
    });
</script>
