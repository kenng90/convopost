<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\EventCatalogService;
use Modules\Reminders\Services\GoogleCalendarService;

class BookingSettingsController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly BookingCatalogService $catalogService,
        private readonly EventCatalogService $eventCatalogService
    ) {
    }

    public function index()
    {
        $this->ownerAndStaffOnly();

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $company = $this->getCompany();

        return view('reminders::booking-settings.index', [
            'setup' => [
                'title' => __('Booking settings'),
                'action_link' => route('reminders.reservations.index'),
                'action_name' => __('Back to appointments'),
                'iscontent' => true,
            ],
            'connected' => $this->googleCalendarService->isConnected($user),
            'calendarId' => $this->googleCalendarService->calendarId($user),
            'connectedAt' => $user->getConfig('google_calendar_connected_at'),
            'redirectUri' => route('reminders.google.callback', [], true),
            'catalogUrl' => $company ? route('reminders.booking.catalog', ['subdomain' => $company->subdomain]) : null,
            'eventsCatalogUrl' => $company ? route('reminders.booking.events', ['subdomain' => $company->subdomain]) : null,
            'eventsEnabled' => $company ? $this->eventCatalogService->eventsEnabled($company) : false,
            'bookingContactsInInbox' => filter_var($company?->getConfig('BOOKING_CONTACTS_IN_INBOX', 'false'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function updateInbox(Request $request)
    {
        $this->ownerAndStaffOnly();

        $company = $this->getCompany();

        if (! $company) {
            abort(403);
        }

        $company->setConfig(
            'BOOKING_CONTACTS_IN_INBOX',
            $request->boolean('booking_contacts_in_inbox') ? 'true' : 'false'
        );

        return redirect()
            ->route('reminders.booking-settings.index')
            ->withStatus(__('Inbox settings updated.'));
    }

    public function updateCalendar(Request $request)
    {
        $this->ownerAndStaffOnly();

        $request->validate([
            'google_calendar_id' => 'required|string|max:255',
        ]);

        auth()->user()->setConfig('google_calendar_id', $request->google_calendar_id);

        return redirect()
            ->route('reminders.booking-settings.index')
            ->withStatus(__('Calendar ID updated.'));
    }

    public function widgetCatalog(Request $request, string $subdomain)
    {
        $company = Company::where('subdomain', $subdomain)->firstOrFail();
        $services = $this->catalogService->bookableServicesForCompany($company);

        return view('reminders::booking.catalog', [
            'company' => $company,
            'services' => $services,
            'token' => $request->query('token', ''),
        ]);
    }

    public function widget(Request $request, string $subdomain, string $sourceName)
    {
        $company = Company::where('subdomain', $subdomain)->firstOrFail();
        $source = Source::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', $sourceName)
            ->where('is_bookable', true)
            ->firstOrFail();

        return view('reminders::booking.widget', [
            'company' => $company,
            'source' => $source,
            'services' => $this->catalogService->bookableServicesForCompany($company),
            'token' => $request->query('token', ''),
            'showServicePicker' => false,
        ]);
    }

    public function eventsCatalog(Request $request, string $subdomain)
    {
        $company = Company::where('subdomain', $subdomain)->firstOrFail();

        abort_unless($this->eventCatalogService->eventsEnabled($company), 404);

        return view('reminders::booking.events-catalog', [
            'company' => $company,
            'events' => $this->eventCatalogService->publishedEventsForCompany($company),
            'token' => $request->query('token', ''),
        ]);
    }

    public function eventRegister(Request $request, string $subdomain, int $occurrence)
    {
        $company = Company::where('subdomain', $subdomain)->firstOrFail();

        abort_unless($this->eventCatalogService->eventsEnabled($company), 404);

        $occurrenceModel = $this->eventCatalogService->findRegisterableOccurrence($company, $occurrence);

        abort_unless($occurrenceModel && $occurrenceModel->isRegisterable(), 404);

        $event = $this->eventCatalogService->formatEvent($occurrenceModel->event);
        $formattedOccurrence = collect($event['occurrences'])->firstWhere('id', $occurrenceModel->id);

        return view('reminders::booking.event-register', [
            'company' => $company,
            'event' => $event,
            'occurrence' => $formattedOccurrence,
            'token' => $request->query('token', ''),
        ]);
    }
}
