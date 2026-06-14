<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;

class GoogleCalendarService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const CALENDAR_BASE = 'https://www.googleapis.com/calendar/v3';

    public function isConnected(User $user): bool
    {
        return (bool) $user->getConfig('google_calendar_refresh_token');
    }

    public function calendarId(User $user): string
    {
        return $user->getConfig('google_calendar_id', 'primary') ?: 'primary';
    }

    /**
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    public function busyBlocks(User $user, Carbon $rangeStart, Carbon $rangeEnd, string $timezone): array
    {
        if (! $this->isConnected($user)) {
            return [];
        }

        $accessToken = $this->accessToken($user);
        if (! $accessToken) {
            return [];
        }

        $response = Http::withToken($accessToken)->post(self::CALENDAR_BASE.'/freeBusy', [
            'timeMin' => $rangeStart->copy()->timezone('UTC')->toRfc3339String(),
            'timeMax' => $rangeEnd->copy()->timezone('UTC')->toRfc3339String(),
            'timeZone' => $timezone,
            'items' => [
                ['id' => $this->calendarId($user)],
            ],
        ]);

        if (! $response->successful()) {
            Log::warning('Google Calendar freeBusy failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return [];
        }

        $calendars = $response->json('calendars.'.$this->calendarId($user).'.busy', []);

        return collect($calendars)->map(function (array $block) use ($timezone) {
            return [
                'start' => Carbon::parse($block['start'])->timezone($timezone),
                'end' => Carbon::parse($block['end'])->timezone($timezone),
            ];
        })->all();
    }

    public function createEventForAppointmentStaff(
        AppointmentStaff $member,
        Reservation $reservation,
        Source $source,
        ?User $fallbackHost = null
    ): ?array {
        $calendarUser = $member->calendarUser();
        if ($calendarUser && $this->isConnected($calendarUser)) {
            $eventId = $this->createEvent($calendarUser, $reservation, $source);

            return $eventId ? ['event_id' => $eventId, 'calendar_user_id' => $calendarUser->id] : null;
        }

        $host = $fallbackHost ?? $this->resolveCompanyCalendarHost($member->company_id);
        if (! $host || ! $this->isConnected($host) || ! $member->email) {
            return null;
        }

        $eventId = $this->createEventWithAttendee($host, $reservation, $source, $member->email, $member->name);

        return $eventId ? ['event_id' => $eventId, 'calendar_user_id' => $host->id] : null;
    }

    public function updateEventForReservation(User $calendarUser, Reservation $reservation, Source $source, ?string $attendeeEmail = null): bool
    {
        $eventId = $reservation->google_event_id ?: $reservation->external_id;
        if (! $eventId || ! $this->isConnected($calendarUser)) {
            return false;
        }

        $accessToken = $this->accessToken($calendarUser);
        if (! $accessToken) {
            return false;
        }

        $contactName = $reservation->contact?->name ?? 'Customer';
        $payload = [
            'summary' => "{$source->name} — {$contactName}",
            'start' => [
                'dateTime' => Carbon::parse($reservation->start_date)->toRfc3339String(),
                'timeZone' => $source->timezone,
            ],
            'end' => [
                'dateTime' => Carbon::parse($reservation->end_date)->toRfc3339String(),
                'timeZone' => $source->timezone,
            ],
        ];

        if ($attendeeEmail) {
            $payload['attendees'] = [['email' => $attendeeEmail]];
        }

        $response = Http::withToken($accessToken)->patch(
            self::CALENDAR_BASE.'/calendars/'.urlencode($this->calendarId($calendarUser)).'/events/'.urlencode($eventId),
            $payload
        );

        return $response->successful();
    }

    public function deleteEventForReservation(User $calendarUser, Reservation $reservation): bool
    {
        $eventId = $reservation->google_event_id ?: $reservation->external_id;

        return $this->deleteEvent($calendarUser, $eventId);
    }

    public function createEventWithAttendee(
        User $host,
        Reservation $reservation,
        Source $source,
        string $attendeeEmail,
        ?string $attendeeName = null
    ): ?string {
        if (! $this->isConnected($host)) {
            return null;
        }

        $accessToken = $this->accessToken($host);
        if (! $accessToken) {
            return null;
        }

        $contactName = $reservation->contact?->name ?? 'Customer';

        $response = Http::withToken($accessToken)->post(
            self::CALENDAR_BASE.'/calendars/'.urlencode($this->calendarId($host)).'/events',
            [
                'summary' => "{$source->name} — {$contactName}",
                'description' => 'Reservation #'.$reservation->id.($attendeeName ? " with {$attendeeName}" : ''),
                'start' => [
                    'dateTime' => Carbon::parse($reservation->start_date)->toRfc3339String(),
                    'timeZone' => $source->timezone,
                ],
                'end' => [
                    'dateTime' => Carbon::parse($reservation->end_date)->toRfc3339String(),
                    'timeZone' => $source->timezone,
                ],
                'attendees' => [
                    ['email' => $attendeeEmail],
                ],
                'extendedProperties' => [
                    'private' => [
                        'convocon_reservation_id' => (string) $reservation->id,
                    ],
                ],
            ]
        );

        if (! $response->successful()) {
            Log::warning('Google Calendar attendee event create failed', [
                'reservation_id' => $reservation->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        return $response->json('id');
    }

    public function resolveCompanyCalendarHost(int $companyId): ?User
    {
        $company = Company::find($companyId);
        if (! $company) {
            return null;
        }

        $owner = User::find($company->user_id);
        if ($owner && $this->isConnected($owner)) {
            return $owner;
        }

        return User::query()
            ->where('company_id', $companyId)
            ->get()
            ->first(fn (User $user) => $this->isConnected($user));
    }

    public function createEvent(User $user, Reservation $reservation, Source $source): ?string
    {
        if (! $this->isConnected($user)) {
            return null;
        }

        $accessToken = $this->accessToken($user);
        if (! $accessToken) {
            return null;
        }

        $contactName = $reservation->contact?->name ?? 'Customer';
        $sourceName = $source->name;

        $response = Http::withToken($accessToken)->post(
            self::CALENDAR_BASE.'/calendars/'.urlencode($this->calendarId($user)).'/events',
            [
                'summary' => "{$sourceName} — {$contactName}",
                'description' => 'Reservation #'.$reservation->id,
                'start' => [
                    'dateTime' => Carbon::parse($reservation->start_date)->toRfc3339String(),
                    'timeZone' => $source->timezone,
                ],
                'end' => [
                    'dateTime' => Carbon::parse($reservation->end_date)->toRfc3339String(),
                    'timeZone' => $source->timezone,
                ],
                'extendedProperties' => [
                    'private' => [
                        'convocon_reservation_id' => (string) $reservation->id,
                    ],
                ],
            ]
        );

        if (! $response->successful()) {
            Log::warning('Google Calendar event create failed', [
                'reservation_id' => $reservation->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        return $response->json('id');
    }

    public function updateEvent(User $user, Reservation $reservation, Source $source): bool
    {
        $eventId = $reservation->google_event_id ?: $reservation->external_id;
        if (! $eventId || ! $this->isConnected($user)) {
            return false;
        }

        $accessToken = $this->accessToken($user);
        if (! $accessToken) {
            return false;
        }

        $contactName = $reservation->contact?->name ?? 'Customer';

        $response = Http::withToken($accessToken)->patch(
            self::CALENDAR_BASE.'/calendars/'.urlencode($this->calendarId($user)).'/events/'.urlencode($eventId),
            [
                'summary' => "{$source->name} — {$contactName}",
                'start' => [
                    'dateTime' => Carbon::parse($reservation->start_date)->toRfc3339String(),
                    'timeZone' => $source->timezone,
                ],
                'end' => [
                    'dateTime' => Carbon::parse($reservation->end_date)->toRfc3339String(),
                    'timeZone' => $source->timezone,
                ],
            ]
        );

        return $response->successful();
    }

    public function deleteEvent(User $user, ?string $eventId): bool
    {
        if (! $eventId || ! $this->isConnected($user)) {
            return false;
        }

        $accessToken = $this->accessToken($user);
        if (! $accessToken) {
            return false;
        }

        $response = Http::withToken($accessToken)->delete(
            self::CALENDAR_BASE.'/calendars/'.urlencode($this->calendarId($user)).'/events/'.urlencode($eventId)
        );

        return $response->successful() || $response->status() === 404 || $response->status() === 410;
    }

    public function storeTokens(User $user, string $accessToken, ?string $refreshToken, ?int $expiresIn): void
    {
        $user->setConfig('google_calendar_access_token', $accessToken);

        if ($refreshToken) {
            $user->setConfig('google_calendar_refresh_token', $refreshToken);
        }

        if ($expiresIn) {
            $user->setConfig(
                'google_calendar_token_expires_at',
                now()->addSeconds($expiresIn)->toDateTimeString()
            );
        }

        $user->setConfig('google_calendar_connected_at', now()->toDateTimeString());
    }

    public function disconnect(User $user): void
    {
        foreach ([
            'google_calendar_access_token',
            'google_calendar_refresh_token',
            'google_calendar_token_expires_at',
            'google_calendar_connected_at',
            'google_calendar_id',
        ] as $key) {
            $user->setConfig($key, '');
        }
    }

    private function accessToken(User $user): ?string
    {
        $expiresAt = $user->getConfig('google_calendar_token_expires_at');
        $accessToken = $user->getConfig('google_calendar_access_token');

        if ($accessToken && $expiresAt && Carbon::parse($expiresAt)->isFuture()) {
            return $accessToken;
        }

        $refreshToken = $user->getConfig('google_calendar_refresh_token');
        if (! $refreshToken) {
            return null;
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            Log::warning('Google Calendar token refresh failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
            ]);

            return null;
        }

        $this->storeTokens(
            $user,
            $response->json('access_token'),
            null,
            (int) $response->json('expires_in', 3600)
        );

        return $response->json('access_token');
    }
}
