<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Api\PublicApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\BookingPaymentService;
use Modules\Reminders\Services\ReservationBookingService;

class BookingsController extends Controller
{
    public function __construct(
        private readonly ReservationBookingService $bookingService,
        private readonly AvailabilityService $availabilityService,
        private readonly BookingCatalogService $catalogService,
        private readonly BookingPaymentService $bookingPaymentService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $limit = min(max((int) $request->input('limit', 50), 1), 100);
        $query = Reservation::query()->where('company_id', $company->id)->orderByDesc('id');

        if ($request->filled('cursor')) {
            $query->where('id', '<', (int) $request->input('cursor'));
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return PublicApiResponse::success(
            $rows->map(fn (Reservation $reservation) => $this->present($reservation))->values()->all(),
            200,
            [
                'limit' => $limit,
                'next_cursor' => $hasMore ? (string) $rows->last()?->id : null,
                'has_more' => $hasMore,
            ]
        );
    }

    public function services(Request $request): JsonResponse
    {
        return PublicApiResponse::success($this->catalogService->bookableServicesForCompany($this->company($request)));
    }

    public function availability(Request $request): JsonResponse
    {
        $request->validate([
            'source' => 'required',
            'date' => 'nullable|date_format:Y-m-d',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
        ]);

        $source = $this->resolveSource($this->company($request), $request->input('source'));
        $duration = $request->filled('duration_minutes') ? (int) $request->duration_minutes : null;

        if ($request->filled('date')) {
            $slots = $this->availabilityService->slotsForDate($source, $request->date, $duration);

            return PublicApiResponse::success([
                'date' => $request->date,
                'source' => $source->name,
                'slots' => $slots,
            ]);
        }

        $from = now($source->timezone)->startOfDay();
        $to = $from->copy()->addDays((int) $source->max_advance_days);

        return PublicApiResponse::success([
            'source' => $source->name,
            'dates' => $this->availabilityService->availableDates($source, $from, $to, $duration),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required',
            'name' => 'required',
            'source' => 'required',
            'slot_id' => 'required_without_all:start_date,end_date',
            'start_date' => 'required_without:slot_id|required_with:end_date|date',
            'end_date' => 'required_without:slot_id|required_with:start_date|date|after:start_date',
        ]);

        try {
            $reservation = $this->bookingService->book($this->company($request), $request->only([
                'phone', 'name', 'source', 'slot_id', 'start_date', 'end_date', 'duration_minutes', 'staff_user_id', 'external_id',
            ]));
        } catch (\InvalidArgumentException $exception) {
            throw new HttpResponseException(PublicApiResponse::error('invalid_request', $exception->getMessage(), 422));
        } catch (\RuntimeException $exception) {
            throw new HttpResponseException(PublicApiResponse::error('conflict', $exception->getMessage(), 409));
        }

        return PublicApiResponse::success($this->present($reservation), 201);
    }

    public function show(Request $request, int $booking): JsonResponse
    {
        return PublicApiResponse::success($this->present($this->findReservation($request, $booking)));
    }

    public function cancel(Request $request, int $booking): JsonResponse
    {
        $reservation = $this->bookingService->cancel($this->findReservation($request, $booking));

        return PublicApiResponse::success($this->present($reservation));
    }

    public function reschedule(Request $request, int $booking): JsonResponse
    {
        $request->validate([
            'slot_id' => 'required_without_all:start_date,end_date',
            'start_date' => 'required_without:slot_id|required_with:end_date|date',
            'end_date' => 'required_without:slot_id|required_with:start_date|date|after:start_date',
        ]);

        try {
            $reservation = $this->bookingService->reschedule($this->findReservation($request, $booking), $request->only([
                'slot_id', 'start_date', 'end_date', 'duration_minutes', 'staff_user_id',
            ]));
        } catch (\InvalidArgumentException $exception) {
            throw new HttpResponseException(PublicApiResponse::error('invalid_request', $exception->getMessage(), 422));
        } catch (\RuntimeException $exception) {
            throw new HttpResponseException(PublicApiResponse::error('conflict', $exception->getMessage(), 409));
        }

        return PublicApiResponse::success($this->present($reservation));
    }

    public function pay(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required',
            'name' => 'required',
            'source' => 'required',
        ]);

        try {
            $result = $this->bookingPaymentService->initiateAppointmentPayment($this->company($request), $request->only([
                'phone', 'name', 'source', 'slot_id', 'start_date', 'end_date', 'duration_minutes', 'staff_user_id', 'external_id',
            ]));
        } catch (\InvalidArgumentException $exception) {
            throw new HttpResponseException(PublicApiResponse::error('invalid_request', $exception->getMessage(), 422));
        } catch (\RuntimeException $exception) {
            throw new HttpResponseException(PublicApiResponse::error('conflict', $exception->getMessage(), 409));
        }

        return PublicApiResponse::success([
            'requires_action' => $result['requires_action'],
            'invoice_public_uuid' => $result['invoice']?->public_uuid,
            'payment' => $result['payment'],
            'reservation' => $result['reservation'] ?? null,
        ], $result['requires_action'] ? 202 : 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'status' => $reservation->status,
            'start_date' => optional($reservation->start_date)?->toIso8601String(),
            'end_date' => optional($reservation->end_date)?->toIso8601String(),
            'duration_minutes' => $reservation->duration_minutes,
            'contact_id' => $reservation->contact_id,
            'source_id' => $reservation->source_id,
            'external_id' => $reservation->external_id,
        ];
    }

    private function findReservation(Request $request, int $id): Reservation
    {
        $reservation = Reservation::query()
            ->where('company_id', $this->company($request)->id)
            ->find($id);

        if (! $reservation) {
            throw new HttpResponseException(PublicApiResponse::error('not_found', 'Booking not found', 404));
        }

        return $reservation;
    }

    private function resolveSource(Company $company, string $sourceRef): Source
    {
        $query = Source::queryForCompany($company->id)->where('is_bookable', true);

        if (is_numeric($sourceRef)) {
            $source = $query->where('id', (int) $sourceRef)->first();
        } else {
            $source = $query->where('name', $sourceRef)->first();
        }

        if (! $source) {
            throw new HttpResponseException(PublicApiResponse::error('not_found', 'Booking service not found', 404));
        }

        return $source;
    }

    private function company(Request $request): Company
    {
        return $request->attributes->get('public_api_company');
    }
}
