<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingManageTokenService;
use Modules\Reminders\Services\BookingPublicKeyService;
use Modules\Reminders\Services\EventCatalogService;
use Modules\Reminders\Services\EventRegistrationService;
use Modules\Reminders\Services\ReservationBookingService;

class BookingManageController extends Controller
{
    public function __construct(
        private readonly BookingManageTokenService $tokens,
        private readonly BookingPublicKeyService $bookingPublicKeyService,
        private readonly ReservationBookingService $bookingService,
        private readonly EventRegistrationService $registrationService,
        private readonly AvailabilityService $availabilityService,
        private readonly EventCatalogService $eventCatalogService,
    ) {
    }

    public function index(Request $request, string $subdomain): View
    {
        $company = Company::where('subdomain', $subdomain)->firstOrFail();

        return view('reminders::booking.manage', [
            'company' => $company,
            'bookingKey' => $this->bookingPublicKeyService->ensureKey($company),
            'initialType' => $request->query('type') === 'events' ? 'events' : 'appointments',
            'eventsEnabled' => $this->eventCatalogService->eventsEnabled($company),
            'tokenPayload' => null,
            'record' => null,
        ]);
    }

    public function showReservation(Request $request, string $subdomain, string $token): View|JsonResponse
    {
        $company = Company::where('subdomain', $subdomain)->firstOrFail();
        $payload = $this->tokens->decode($token);

        abort_unless($payload && $payload['type'] === 'r' && $payload['company_id'] === (int) $company->id, 404);

        $reservation = Reservation::withoutGlobalScopes()
            ->with(['contact', 'source', 'appointmentStaffMember'])
            ->where('company_id', $company->id)
            ->where('id', $payload['id'])
            ->firstOrFail();

        return view('reminders::booking.manage', [
            'company' => $company,
            'bookingKey' => $this->bookingPublicKeyService->ensureKey($company),
            'initialType' => 'appointments',
            'eventsEnabled' => $this->eventCatalogService->eventsEnabled($company),
            'tokenPayload' => ['type' => 'r', 'token' => $token],
            'record' => $this->formatReservation($reservation),
        ]);
    }

    public function showRegistration(Request $request, string $subdomain, string $token): View
    {
        $company = Company::where('subdomain', $subdomain)->firstOrFail();
        $payload = $this->tokens->decode($token);

        abort_unless($payload && $payload['type'] === 'e' && $payload['company_id'] === (int) $company->id, 404);

        $registration = EventRegistration::withoutGlobalScopes()
            ->with(['contact', 'event', 'occurrence'])
            ->where('company_id', $company->id)
            ->where('id', $payload['id'])
            ->firstOrFail();

        return view('reminders::booking.manage', [
            'company' => $company,
            'bookingKey' => $this->bookingPublicKeyService->ensureKey($company),
            'initialType' => 'events',
            'eventsEnabled' => true,
            'tokenPayload' => ['type' => 'e', 'token' => $token],
            'record' => $this->formatRegistration($registration),
        ]);
    }

    public function lookup(Request $request, string $subdomain): JsonResponse
    {
        $company = Company::where('subdomain', $subdomain)->firstOrFail();

        $key = 'booking-manage-lookup:'.$request->ip().':'.$company->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Too many attempts. Please try again later.'),
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $data = $request->validate([
            'type' => 'required|in:appointments,events',
            'reference' => 'required|string|max:64',
            'verify' => 'required|string|max:120',
        ]);

        $reference = preg_replace('/[^A-Za-z0-9]/', '', $data['reference']) ?: '';
        $verify = trim($data['verify']);

        if ($data['type'] === 'events') {
            $registration = $this->findRegistration($company, $reference);
            if (! $registration || ! $this->verifyContact($registration->contact?->name, $registration->contact?->phone, $verify)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('We could not find an event registration with those details.'),
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'redirect' => $this->tokens->makeRegistrationUrl($company, $registration),
            ]);
        }

        $reservation = $this->findReservation($company, $reference);
        if (! $reservation || ! $this->verifyContact($reservation->contact?->name, $reservation->contact?->phone, $verify)) {
            return response()->json([
                'status' => 'error',
                'message' => __('We could not find a booking with those details.'),
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'redirect' => $this->tokens->makeReservationUrl($company, $reservation),
        ]);
    }

    public function cancelReservation(Request $request, string $subdomain, string $token): JsonResponse
    {
        $company = Company::where('subdomain', $subdomain)->firstOrFail();
        $reservation = $this->reservationFromToken($company, $token);

        if (! $reservation->isActive()) {
            return response()->json([
                'status' => 'error',
                'message' => __('This booking can no longer be cancelled.'),
            ], 422);
        }

        $reservation = $this->bookingService->cancel($reservation, 'public_manage');

        return response()->json([
            'status' => 'success',
            'record' => $this->formatReservation($reservation->fresh(['contact', 'source', 'appointmentStaffMember'])),
        ]);
    }

    public function rescheduleReservation(Request $request, string $subdomain, string $token): JsonResponse
    {
        $company = Company::where('subdomain', $subdomain)->firstOrFail();
        $reservation = $this->reservationFromToken($company, $token);

        if (! $reservation->isActive()) {
            return response()->json([
                'status' => 'error',
                'message' => __('This booking can no longer be rescheduled.'),
            ], 422);
        }

        $request->validate([
            'slot_id' => 'required_without_all:start_date,end_date|string',
            'start_date' => 'required_without:slot_id|required_with:end_date|date',
            'end_date' => 'required_without:slot_id|required_with:start_date|date|after:start_date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
        ]);

        try {
            $reservation = $this->bookingService->reschedule($reservation, $request->only([
                'slot_id',
                'start_date',
                'end_date',
                'duration_minutes',
                'staff_user_id',
            ]));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'status' => 'success',
            'record' => $this->formatReservation($reservation->fresh(['contact', 'source', 'appointmentStaffMember'])),
        ]);
    }

    public function cancelRegistration(Request $request, string $subdomain, string $token): JsonResponse
    {
        $company = Company::where('subdomain', $subdomain)->firstOrFail();
        $registration = $this->registrationFromToken($company, $token);

        if (! $registration->isActive()) {
            return response()->json([
                'status' => 'error',
                'message' => __('This registration can no longer be cancelled.'),
            ], 422);
        }

        $registration = $this->registrationService->cancel($registration);

        return response()->json([
            'status' => 'success',
            'record' => $this->formatRegistration($registration->fresh(['contact', 'event', 'occurrence'])),
        ]);
    }

    private function reservationFromToken(Company $company, string $token): Reservation
    {
        $payload = $this->tokens->decode($token);
        abort_unless($payload && $payload['type'] === 'r' && $payload['company_id'] === (int) $company->id, 404);

        return Reservation::withoutGlobalScopes()
            ->with(['contact', 'source', 'appointmentStaffMember'])
            ->where('company_id', $company->id)
            ->where('id', $payload['id'])
            ->firstOrFail();
    }

    private function registrationFromToken(Company $company, string $token): EventRegistration
    {
        $payload = $this->tokens->decode($token);
        abort_unless($payload && $payload['type'] === 'e' && $payload['company_id'] === (int) $company->id, 404);

        return EventRegistration::withoutGlobalScopes()
            ->with(['contact', 'event', 'occurrence'])
            ->where('company_id', $company->id)
            ->where('id', $payload['id'])
            ->firstOrFail();
    }

    private function findReservation(Company $company, string $reference): ?Reservation
    {
        $query = Reservation::withoutGlobalScopes()
            ->with(['contact', 'source', 'appointmentStaffMember'])
            ->where('company_id', $company->id)
            ->whereNull('cancelled_at')
            ->where('status', 1)
            ->where('start_date', '>=', now()->subDay());

        if (ctype_digit($reference)) {
            return $query->where('id', (int) $reference)->first();
        }

        return $query->where('external_id', $reference)->first();
    }

    private function findRegistration(Company $company, string $reference): ?EventRegistration
    {
        $query = EventRegistration::withoutGlobalScopes()
            ->with(['contact', 'event', 'occurrence'])
            ->where('company_id', $company->id)
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->whereNull('cancelled_at');

        if (ctype_digit($reference)) {
            return $query->where('id', (int) $reference)->first();
        }

        return $query->where('external_id', $reference)->first();
    }

    private function verifyContact(?string $name, ?string $phone, string $verify): bool
    {
        $verifyNorm = mb_strtolower(trim($verify));
        if ($verifyNorm === '') {
            return false;
        }

        $nameNorm = mb_strtolower(trim((string) $name));
        if ($nameNorm !== '') {
            if ($nameNorm === $verifyNorm) {
                return true;
            }
            $first = explode(' ', $nameNorm)[0] ?? '';
            if ($first !== '' && $first === $verifyNorm) {
                return true;
            }
        }

        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';
        $verifyDigits = preg_replace('/\D+/', '', $verify) ?: '';
        if (strlen($digits) >= 4 && strlen($verifyDigits) >= 4) {
            return str_ends_with($digits, substr($verifyDigits, -4));
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReservation(Reservation $reservation): array
    {
        return [
            'type' => 'appointment',
            'id' => $reservation->id,
            'reference' => $reservation->external_id ?: '#'.$reservation->id,
            'status' => $reservation->displayStatus(),
            'status_label' => $reservation->displayStatusLabel(),
            'can_cancel' => $reservation->isActive() && $reservation->start_date?->isFuture(),
            'can_reschedule' => $reservation->isActive() && $reservation->start_date?->isFuture(),
            'service' => $reservation->source?->name,
            'source' => $reservation->source?->name,
            'duration_minutes' => $reservation->duration_minutes,
            'duration_options' => $reservation->source?->durationOptions() ?? [],
            'timezone' => $reservation->source?->timezone ?? config('app.timezone'),
            'start_date' => $reservation->start_date?->toIso8601String(),
            'end_date' => $reservation->end_date?->toIso8601String(),
            'date_label' => $reservation->start_date?->timezone($reservation->source?->timezone ?? config('app.timezone'))->format('D, M j, Y'),
            'time_label' => $reservation->start_date?->timezone($reservation->source?->timezone ?? config('app.timezone'))->format('g:i A'),
            'name' => $reservation->contact?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRegistration(EventRegistration $registration): array
    {
        $starts = $registration->occurrence?->starts_at;

        return [
            'type' => 'event',
            'id' => $registration->id,
            'reference' => $registration->external_id ?: '#'.$registration->id,
            'status' => $registration->displayStatus(),
            'status_label' => $registration->displayStatusLabel(),
            'can_cancel' => $registration->isActive() && $starts?->isFuture(),
            'can_reschedule' => false,
            'service' => $registration->event?->title,
            'source' => null,
            'duration_minutes' => null,
            'duration_options' => [],
            'timezone' => $registration->occurrence?->timezone ?? config('app.timezone'),
            'start_date' => $starts?->toIso8601String(),
            'end_date' => $registration->occurrence?->ends_at?->toIso8601String(),
            'date_label' => $starts?->format('D, M j, Y'),
            'time_label' => $starts?->format('g:i A'),
            'name' => $registration->contact?->name,
            'party_size' => $registration->party_size,
        ];
    }
}
