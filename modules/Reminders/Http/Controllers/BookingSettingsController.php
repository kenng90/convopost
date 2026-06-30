<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\BookingPaymentService;
use Modules\Reminders\Services\BookingPublicKeyService;
use Modules\Reminders\Services\EventCatalogService;
use Modules\Reminders\Services\GoogleCalendarService;
use Modules\Reminders\Support\BookingPaymentConfig;
use Modules\Wpbox\Support\PhoneNormalizer;

class BookingSettingsController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly BookingCatalogService $catalogService,
        private readonly BookingPublicKeyService $bookingPublicKeyService,
        private readonly EventCatalogService $eventCatalogService,
        private readonly BookingPaymentService $bookingPaymentService
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
                'action_link' => route('reminders.overview.index'),
                'action_name' => __('Back to bookings'),
                'iscontent' => true,
            ],
            'connected' => $this->googleCalendarService->isConnected($user),
            'calendarId' => $this->googleCalendarService->calendarId($user),
            'connectedAt' => $user->getConfig('google_calendar_connected_at'),
            'redirectUri' => route('reminders.google.callback', [], true),
            'catalogUrl' => $company ? route('reminders.booking.catalog', ['subdomain' => $company->subdomain]) : null,
            'eventsCatalogUrl' => $company ? route('reminders.booking.events', ['subdomain' => $company->subdomain]) : null,
            'bookingPublicKey' => $company ? $this->bookingPublicKeyService->ensureKey($company) : null,
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

    public function regenerateBookingKey()
    {
        $this->ownerAndStaffOnly();

        $company = $this->getCompany();

        if (! $company) {
            abort(403);
        }

        $this->bookingPublicKeyService->rotate($company);

        return redirect()
            ->route('reminders.booking-settings.index')
            ->withStatus(__('Booking public key regenerated. Update any embedded widgets using the old key.'));
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
            'eventsEnabled' => $this->eventCatalogService->eventsEnabled($company),
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
            'bookingKey' => $this->bookingPublicKeyService->ensureKey($company),
            'showServicePicker' => false,
            'bookingPhoneCountry' => app(PhoneNormalizer::class)->isoForCompany($company),
            'paymentConfig' => BookingPaymentConfig::fromSource($source),
            'mpesaConfigured' => $this->bookingPaymentService->mpesaConfigured($company),
        ]);
    }

    public function eventsCatalog(Request $request, string $subdomain)
    {
        $company = Company::where('subdomain', $subdomain)->firstOrFail();

        abort_unless($this->eventCatalogService->eventsEnabled($company), 404);

        return view('reminders::booking.events-catalog', [
            'company' => $company,
            'events' => $this->eventCatalogService->publishedEventsForCompany($company),
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
            'bookingKey' => $this->bookingPublicKeyService->ensureKey($company),
            'bookingPhoneCountry' => app(PhoneNormalizer::class)->isoForCompany($company),
            'paymentConfig' => BookingPaymentConfig::fromEvent($occurrenceModel->event),
            'mpesaConfigured' => $this->bookingPaymentService->mpesaConfigured($company),
        ]);
    }
}
