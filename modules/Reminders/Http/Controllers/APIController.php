<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Remineder;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\BookingChatPanelService;
use Modules\Reminders\Services\BookingPublicKeyService;
use Modules\Reminders\Services\EventCatalogService;
use Modules\Reminders\Services\EventRegistrationService;
use Modules\Reminders\Services\ReservationBookingService;
use Modules\Wpbox\Traits\Contacts;

class APIController extends Controller
{
    use Contacts;

    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly ReservationBookingService $bookingService,
        private readonly BookingCatalogService $catalogService,
        private readonly BookingChatPanelService $chatPanelService,
        private readonly BookingPublicKeyService $bookingPublicKeyService,
        private readonly EventCatalogService $eventCatalogService,
        private readonly EventRegistrationService $eventRegistrationService
    ) {
    }

    private function authenticate(Request $request, \Closure $next, $rules = ['token' => 'required'])
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        if (Auth::check()) {
            return $next($request);
        }

        $token = PersonalAccessToken::findToken($request->token);
        if (! $token) {
            return response()->json(['status' => 'error', 'message' => 'Invalid token'], 401);
        }

        $user = User::findOrFail($token->tokenable_id);
        Auth::login($user);

        return $next($request);
    }

    private function publicBookingAuthRules(array $rules = []): array
    {
        return array_merge([
            'booking_key' => 'required_without:token|string',
            'token' => 'required_without:booking_key|string',
        ], $rules);
    }

    private function authenticatePublicBooking(Request $request, \Closure $next, array $rules = [])
    {
        $validator = Validator::make($request->all(), $this->publicBookingAuthRules($rules));

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        if (Auth::check()) {
            return $next($request);
        }

        $bookingKey = $request->input('booking_key') ?? $request->header('X-Booking-Key');
        $legacyToken = $request->input('token');

        if (! $bookingKey && $this->bookingPublicKeyService->isBookingKey($legacyToken)) {
            $bookingKey = $legacyToken;
        }

        if ($bookingKey) {
            $company = $this->bookingPublicKeyService->companyForKey($bookingKey);

            if (! $company) {
                return response()->json(['status' => 'error', 'message' => 'Invalid booking key'], 401);
            }

            if ($request->filled('subdomain') && $request->subdomain !== $company->subdomain) {
                return response()->json(['status' => 'error', 'message' => 'Invalid booking key for this company'], 403);
            }

            $request->attributes->set('booking_company', $company);
            session(['company_id' => $company->id]);

            return $next($request);
        }

        if ($legacyToken) {
            $token = PersonalAccessToken::findToken($legacyToken);

            if ($token) {
                Auth::login(User::findOrFail($token->tokenable_id));

                return $next($request);
            }
        }

        return response()->json(['status' => 'error', 'message' => 'Invalid credentials'], 401);
    }

    private function resolveBookingCompany(Request $request): Company
    {
        $company = $request->attributes->get('booking_company');

        if ($company instanceof Company) {
            return $company;
        }

        $company = $this->getCompany();

        if (! $company) {
            abort(401, 'Company not resolved');
        }

        return $company;
    }

    public function getReminders(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $reminders = Remineder::where('user_id', Auth::id())->get();

            return response()->json(['status' => 'success', 'reminders' => $reminders]);
        });
    }

    public function getContactReservations(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $panel = $this->chatPanelService->forContact($company, (int) $request->contact_id);

            return response()->json([
                'status' => 'success',
                'reservations' => $panel['appointments'],
                'appointments' => $panel['appointments'],
            ]);
        }, [
            'token' => 'required',
            'contact_id' => 'required|integer',
        ]);
    }

    public function getContactBookings(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $panel = $this->chatPanelService->forContact($company, (int) $request->contact_id);

            return response()->json([
                'status' => 'success',
                ...$panel,
            ]);
        }, [
            'token' => 'required',
            'contact_id' => 'required|integer',
        ]);
    }

    public function getContactEventRegistrations(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $registrations = EventRegistration::query()
                ->with(['event', 'occurrence'])
                ->where('contact_id', $request->contact_id)
                ->orderByDesc('registered_at')
                ->get()
                ->map(fn (EventRegistration $registration) => $this->chatPanelService->formatEventRegistration($registration))
                ->values();

            return response()->json(['status' => 'success', 'registrations' => $registrations]);
        }, [
            'token' => 'required',
            'contact_id' => 'required|integer',
        ]);
    }

    public function events(Request $request)
    {
        return $this->authenticatePublicBooking($request, function ($request) {
            $company = $this->resolveBookingCompany($request);

            if (! $this->eventCatalogService->eventsEnabled($company)) {
                return response()->json(['status' => 'success', 'events' => []]);
            }

            return response()->json([
                'status' => 'success',
                'events' => $this->eventCatalogService->publishedEventsForCompany($company),
            ]);
        });
    }

    public function registerForEvent(Request $request)
    {
        return $this->authenticatePublicBooking($request, function ($request) {
            $company = $this->resolveBookingCompany($request);

            if (! $this->eventCatalogService->eventsEnabled($company)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Events booking is disabled for this company.',
                ], 403);
            }

            try {
                $registration = $this->eventRegistrationService->register($company, $request->only([
                    'occurrence_id',
                    'phone',
                    'name',
                    'party_size',
                    'external_id',
                ]));
            } catch (\InvalidArgumentException $exception) {
                return response()->json([
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ], 422);
            } catch (\RuntimeException $exception) {
                return response()->json([
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ], 409);
            }

            return response()->json(['status' => 'success', 'registration' => $registration->load(['event', 'occurrence', 'contact'])], 201);
        }, [
            'occurrence_id' => 'required|integer',
            'phone' => 'required',
            'name' => 'required',
            'party_size' => 'nullable|integer|min:1|max:100',
        ]);
    }

    public function cancelEventRegistration(Request $request)
    {
        return $this->authenticatePublicBooking($request, function ($request) {
            $company = $this->resolveBookingCompany($request);
            $registration = EventRegistration::where('company_id', $company->id)
                ->where('id', $request->registration_id)
                ->firstOrFail();

            $registration = $this->eventRegistrationService->cancel($registration);

            return response()->json(['status' => 'success', 'registration' => $registration]);
        }, [
            'registration_id' => 'required|integer',
        ]);
    }

    public function createReminder(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string',
                'description' => 'nullable|string',
                'due_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'errors' => $validator->errors()], 400);
            }

            $reminder = Remineder::create([
                'user_id' => Auth::id(),
                'title' => $request->title,
                'description' => $request->description,
                'due_date' => $request->due_date,
            ]);

            return response()->json(['status' => 'success', 'reminder' => $reminder], 201);
        });
    }

    public function getReservations(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $reservations = Reservation::where('user_id', Auth::id())->get();

            return response()->json(['status' => 'success', 'reservations' => $reservations]);
        });
    }

    public function services(Request $request)
    {
        return $this->authenticatePublicBooking($request, function ($request) {
            $company = $this->resolveBookingCompany($request);

            return response()->json([
                'status' => 'success',
                'services' => $this->catalogService->bookableServicesForCompany($company),
            ]);
        });
    }

    public function availability(Request $request)
    {
        return $this->authenticatePublicBooking($request, function ($request) {
            $company = $this->resolveBookingCompany($request);
            $source = $this->resolveSource($company, $request->input('source'));

            $duration = $request->filled('duration_minutes')
                ? (int) $request->duration_minutes
                : null;

            if ($request->filled('date')) {
                $slots = $this->availabilityService->slotsForDate($source, $request->date, $duration);

                return response()->json([
                    'status' => 'success',
                    'date' => $request->date,
                    'source' => $source->name,
                    'duration_minutes' => $this->availabilityService->resolveDuration($source, $duration),
                    'duration_options' => $source->durationOptions(),
                    'slots' => $slots,
                    'available_slots' => collect($slots)->map(fn ($slot) => [
                        'id' => $slot['id'],
                        'title' => $slot['title'],
                    ])->values(),
                ]);
            }

            $from = now($source->timezone)->startOfDay();
            $to = $from->copy()->addDays((int) $source->max_advance_days);
            $dates = $this->availabilityService->availableDates($source, $from, $to, $duration);

            return response()->json([
                'status' => 'success',
                'source' => $source->name,
                'duration_options' => $source->durationOptions(),
                'dates' => $dates,
            ]);
        }, [
            'source' => 'required',
            'date' => 'nullable|date_format:Y-m-d',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
        ]);
    }

    public function createReservation(Request $request)
    {
        return $this->authenticatePublicBooking($request, function ($request) {
            $company = $this->resolveBookingCompany($request);

            try {
                $reservation = $this->bookingService->book($company, $request->only([
                    'phone',
                    'name',
                    'source',
                    'slot_id',
                    'start_date',
                    'end_date',
                    'duration_minutes',
                    'staff_user_id',
                    'external_id',
                ]));
            } catch (\InvalidArgumentException $exception) {
                return response()->json([
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ], 422);
            } catch (\RuntimeException $exception) {
                return response()->json([
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ], 409);
            }

            return response()->json(['status' => 'success', 'reservation' => $reservation], 201);
        }, [
            'phone' => 'required',
            'name' => 'required',
            'source' => 'required',
            'slot_id' => 'required_without_all:start_date,end_date',
            'start_date' => 'required_without:slot_id|required_with:end_date|date',
            'end_date' => 'required_without:slot_id|required_with:start_date|date|after:start_date',
            'staff_user_id' => 'required_without:slot_id|integer',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
        ]);
    }

    public function cancelReservation(Request $request)
    {
        return $this->authenticatePublicBooking($request, function ($request) {
            $company = $this->resolveBookingCompany($request);
            $reservation = Reservation::where('company_id', $company->id)
                ->where('id', $request->reservation_id)
                ->firstOrFail();

            $reservation = $this->bookingService->cancel($reservation);

            return response()->json(['status' => 'success', 'reservation' => $reservation]);
        }, [
            'reservation_id' => 'required|integer',
        ]);
    }

    public function rescheduleReservation(Request $request)
    {
        return $this->authenticatePublicBooking($request, function ($request) {
            $company = $this->resolveBookingCompany($request);
            $reservation = Reservation::where('company_id', $company->id)
                ->where('id', $request->reservation_id)
                ->firstOrFail();

            try {
                $reservation = $this->bookingService->reschedule($reservation, $request->only([
                    'slot_id',
                    'start_date',
                    'end_date',
                    'duration_minutes',
                    'staff_user_id',
                ]));
            } catch (\InvalidArgumentException $exception) {
                return response()->json([
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ], 422);
            } catch (\RuntimeException $exception) {
                return response()->json([
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ], 409);
            }

            return response()->json(['status' => 'success', 'reservation' => $reservation]);
        }, [
            'reservation_id' => 'required|integer',
            'slot_id' => 'required_without_all:start_date,end_date',
            'start_date' => 'required_without:slot_id|required_with:end_date|date',
            'end_date' => 'required_without:slot_id|required_with:start_date|date|after:start_date',
            'staff_user_id' => 'nullable|integer',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
        ]);
    }

    private function resolveSource(Company $company, string $sourceRef): Source
    {
        $query = Source::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_bookable', true);

        if (is_numeric($sourceRef)) {
            return $query->where('id', (int) $sourceRef)->firstOrFail();
        }

        return $query->where('name', $sourceRef)->firstOrFail();
    }
}
