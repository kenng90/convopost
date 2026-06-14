<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Reminders\Models\Remineder;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\ReservationBookingService;
use Modules\Wpbox\Traits\Contacts;

class APIController extends Controller
{
    use Contacts;

    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly ReservationBookingService $bookingService,
        private readonly BookingCatalogService $catalogService
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
            $reservations = Reservation::where('contact_id', $request->contact_id)->get();

            return response()->json(['status' => 'success', 'reservations' => $reservations]);
        });
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
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();

            return response()->json([
                'status' => 'success',
                'services' => $this->catalogService->bookableServicesForCompany($company),
            ]);
        }, [
            'token' => 'required',
        ]);
    }

    public function availability(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
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
            'token' => 'required',
            'source' => 'required',
            'date' => 'nullable|date_format:Y-m-d',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
        ]);
    }

    public function createReservation(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();

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
            'token' => 'required',
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
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
            $reservation = Reservation::where('company_id', $company->id)
                ->where('id', $request->reservation_id)
                ->firstOrFail();

            $reservation = $this->bookingService->cancel($reservation);

            return response()->json(['status' => 'success', 'reservation' => $reservation]);
        }, [
            'token' => 'required',
            'reservation_id' => 'required|integer',
        ]);
    }

    public function rescheduleReservation(Request $request)
    {
        return $this->authenticate($request, function ($request) {
            $company = $this->getCompany();
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
            'token' => 'required',
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
