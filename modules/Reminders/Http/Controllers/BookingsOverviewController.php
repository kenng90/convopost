<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Department;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\EventCatalogService;

class BookingsOverviewController extends Controller
{
    public function __construct(
        private readonly EventCatalogService $eventCatalogService
    ) {
    }

    public function index()
    {
        $this->ownerAndStaffOnly();

        $company = $this->getCompany();
        $now = now();

        $upcomingAppointments = Reservation::query()
            ->where('start_date', '>=', $now)
            ->where('status', 1)
            ->whereNull('cancelled_at')
            ->count();

        $upcomingRegistrations = EventRegistration::query()
            ->where('status', 'confirmed')
            ->whereHas('occurrence', fn ($query) => $query->where('starts_at', '>=', $now))
            ->count();

        $eventsEnabled = $company ? $this->eventCatalogService->eventsEnabled($company) : false;

        return view('reminders::overview.index', [
            'setup' => [
                'title' => __('Bookings'),
                'subtitle' => __('Manage one-to-one appointments and fixed-date events from one place.'),
                'iscontent' => true,
            ],
            'stats' => [
                'upcoming_appointments' => $upcomingAppointments,
                'upcoming_registrations' => $upcomingRegistrations,
                'services' => Source::query()->count(),
                'events' => $eventsEnabled ? Event::query()->count() : null,
                'team_members' => AppointmentStaff::query()->count(),
                'departments' => Department::query()->count(),
            ],
            'eventsEnabled' => $eventsEnabled,
            'catalogUrl' => $company ? route('reminders.booking.catalog', ['subdomain' => $company->subdomain]) : null,
            'eventsCatalogUrl' => $company && $eventsEnabled
                ? route('reminders.booking.events', ['subdomain' => $company->subdomain])
                : null,
            'bookingSettingsUrl' => route('reminders.booking-settings.index'),
        ]);
    }
}
