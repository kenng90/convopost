<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group([
    'middleware' => ['web'],
    'namespace' => 'Modules\Reminders\Http\Controllers',
], function () {
    Route::prefix('api/reminders/reservation')->group(function () {
        Route::post('makeReservation', 'APIController@createReservation');
        Route::post('cancel', 'APIController@cancelReservation');
        Route::post('reschedule', 'APIController@rescheduleReservation');
    });

    Route::prefix('api/reminders')->group(function () {
        Route::get('availability', 'APIController@availability');
        Route::get('services', 'APIController@services');
        Route::get('events', 'APIController@events');
        Route::post('events/register', 'APIController@registerForEvent');
        Route::post('events/cancel-registration', 'APIController@cancelEventRegistration');
        Route::post('get-contact-event-registrations', 'APIController@getContactEventRegistrations');
    });

    Route::get('book/{subdomain}', 'BookingSettingsController@widgetCatalog')
        ->name('reminders.booking.catalog');
    Route::get('book/{subdomain}/events', 'BookingSettingsController@eventsCatalog')
        ->name('reminders.booking.events');
    Route::get('book/{subdomain}/events/{occurrence}', 'BookingSettingsController@eventRegister')
        ->name('reminders.booking.event');
    Route::get('book/{subdomain}/{source}', 'BookingSettingsController@widget')
        ->name('reminders.booking.widget');
});

Route::group([
    'middleware' => ['web', 'impersonate', 'XssSanitizer', 'auth', 'plan.plugin:reminders'],
    'namespace' => 'Modules\Reminders\Http\Controllers',
], function () {

    Route::post('/api/reminders/get-contact-reservations', 'APIController@getContactReservations')->name('reminders.get-contact-reservations');
    Route::post('/api/reminders/get-contact-bookings', 'APIController@getContactBookings')->name('reminders.get-contact-bookings');

    Route::prefix('reminders')->group(function () {

        Route::get('booking-settings', 'BookingSettingsController@index')->name('reminders.booking-settings.index');
        Route::post('booking-settings/calendar', 'BookingSettingsController@updateCalendar')->name('reminders.booking-settings.calendar');
        Route::post('booking-settings/inbox', 'BookingSettingsController@updateInbox')->name('reminders.booking-settings.inbox');
        Route::post('booking-settings/regenerate-key', 'BookingSettingsController@regenerateBookingKey')->name('reminders.booking-settings.regenerate-key');
        Route::get('google/connect', 'GoogleCalendarController@connect')->name('reminders.google.connect');
        Route::get('google/callback', 'GoogleCalendarController@callback')->name('reminders.google.callback');
        Route::get('google/disconnect', 'GoogleCalendarController@disconnect')->name('reminders.google.disconnect');

        Route::get('reminders', 'RemindersController@index')->name('reminders.reminders.index');
        Route::get('reminders/{reminder}/edit', 'RemindersController@edit')->name('reminders.reminders.edit');
        Route::get('reminders/create', 'RemindersController@create')->name('reminders.reminders.create');
        Route::post('reminders', 'RemindersController@store')->name('reminders.reminders.store');
        Route::put('reminders/{reminder}', 'RemindersController@update')->name('reminders.reminders.update');
        Route::get('reminders/del/{reminder}', 'RemindersController@destroy')->name('reminders.reminders.delete');

        Route::get('sources', 'SourcesController@index')->name('reminders.sources.index');
        Route::get('sources/{source}/edit', 'SourcesController@edit')->name('reminders.sources.edit');
        Route::get('sources/create', 'SourcesController@create')->name('reminders.sources.create');
        Route::post('sources', 'SourcesController@store')->name('reminders.sources.store');
        Route::put('sources/{source}', 'SourcesController@update')->name('reminders.sources.update');
        Route::get('sources/del/{source}', 'SourcesController@destroy')->name('reminders.sources.delete');

        Route::get('departments', 'DepartmentsController@index')->name('reminders.departments.index');
        Route::get('departments/create', 'DepartmentsController@create')->name('reminders.departments.create');
        Route::post('departments', 'DepartmentsController@store')->name('reminders.departments.store');
        Route::get('departments/{department}/edit', 'DepartmentsController@edit')->name('reminders.departments.edit');
        Route::put('departments/{department}', 'DepartmentsController@update')->name('reminders.departments.update');
        Route::get('departments/del/{department}', 'DepartmentsController@destroy')->name('reminders.departments.delete');

        Route::get('appointment-staff', 'AppointmentStaffController@index')->name('reminders.appointment-staff.index');
        Route::get('appointment-staff/create', 'AppointmentStaffController@create')->name('reminders.appointment-staff.create');
        Route::post('appointment-staff', 'AppointmentStaffController@store')->name('reminders.appointment-staff.store');
        Route::get('appointment-staff/{appointmentStaff}/edit', 'AppointmentStaffController@edit')->name('reminders.appointment-staff.edit');
        Route::put('appointment-staff/{appointmentStaff}', 'AppointmentStaffController@update')->name('reminders.appointment-staff.update');
        Route::get('appointment-staff/del/{appointmentStaff}', 'AppointmentStaffController@destroy')->name('reminders.appointment-staff.delete');

        Route::get('events', 'EventsController@index')->name('reminders.events.index');
        Route::get('events/create', 'EventsController@create')->name('reminders.events.create');
        Route::post('events', 'EventsController@store')->name('reminders.events.store');
        Route::get('events/del/{event}', 'EventsController@destroy')->name('reminders.events.delete');
        Route::get('events/{event}/edit', 'EventsController@edit')->name('reminders.events.edit');
        Route::put('events/{event}', 'EventsController@update')->name('reminders.events.update');
        Route::post('events/{event}/occurrences', 'EventsController@storeOccurrence')->name('reminders.events.occurrences.store');
        Route::get('events/{event}/occurrences/del/{occurrence}', 'EventsController@destroyOccurrence')->name('reminders.events.occurrences.delete');

        Route::get('event-registrations', 'EventRegistrationsController@index')->name('reminders.event-registrations.index');
        Route::get('event-registrations/{eventRegistration}/open-chat', 'EventRegistrationsController@openChat')->name('reminders.event-registrations.open-chat');
        Route::get('event-registrations/{eventRegistration}/cancel', 'EventRegistrationsController@cancel')->name('reminders.event-registrations.cancel');
        Route::get('event-registrations/{eventRegistration}', 'EventRegistrationsController@show')->name('reminders.event-registrations.show');

        Route::get('reservations', 'ReservationsController@index')->name('reminders.reservations.index');
        Route::get('reservations/export', 'ReservationsController@export')->name('reminders.reservations.export');
        Route::get('reservations/create', 'ReservationsController@create')->name('reminders.reservations.create');
        Route::get('reservations/del/{reservation}', 'ReservationsController@destroy')->name('reminders.reservations.delete');
        Route::get('reservations/{reservation}/open-chat', 'ReservationsController@openChat')->name('reminders.reservations.open-chat');
        Route::get('reservations/{reservation}/edit', 'ReservationsController@edit')->name('reminders.reservations.edit');
        Route::get('reservations/{reservation}', 'ReservationsController@show')->name('reminders.reservations.show');
        Route::post('reservations', 'ReservationsController@store')->name('reminders.reservations.store');
        Route::put('reservations/{reservation}', 'ReservationsController@update')->name('reminders.reservations.update');

    });
});
