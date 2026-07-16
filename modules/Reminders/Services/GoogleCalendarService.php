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

    public function resolvedCalendarId(User $user, ?string $override = null): string
    {
        if ($override !== null && $override !== '') {
            return $override;
        }

        return $this->calendarId($user);
    }

    public function calendarIdForReservation(User $user, Reservation $reservation, ?Source $source = null): string
    {
        if ($reservation->google_calendar_id) {
            return $reservation->google_calendar_id;
        }

        if ($source?->google_calendar_id) {
            return $source->google_calendar_id;
        }

        return $this->calendarId($user);
    }

    /**
     * @return array<int, array{id: string, summary: string, label: string, primary: bool}>
     */
    public function listCalendars(User $user, string $minAccessRole = 'writer'): array
    {
        if (! $this->isConnected($user)) {
            return [];
        }

        $accessToken = $this->accessToken($user);
        if (! $accessToken) {
            return [];
        }

        $response = Http::withToken($accessToken)->get(self::CALENDAR_BASE.'/users/me/calendarList', [
            'minAccessRole' => $minAccessRole,
        ]);

        if (! $response->successful()) {
            Log::warning('Google Calendar list failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return [];
        }

        return collect($response->json('items', []))
            ->map(function (array $item) {
                $summary = $item['summary'] ?? $item['id'];
                $primary = ! empty($item['primary']);

                return [
                    'id' => $item['id'],
                    'summary' => $summary,
                    'label' => $primary ? "{$summary} (".__('Primary').')' : $summary,
                    'primary' => $primary,
                ];
            })
            ->sortByDesc('primary')
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function calendarSelectOptions(User $user): array
    {
        return collect($this->listCalendars($user))
            ->mapWithKeys(fn (array $calendar) => [$calendar['id'] => $calendar['label']])
            ->all();
    }

    public function embedUrl(string $calendarId, string $timezone = 'UTC'): string
    {
        return 'https://calendar.google.com/calendar/embed?'.http_build_query([
            'src' => $calendarId,
            'ctz' => $timezone,
        ]);
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

    /**
     * @return array{
     *     connected: bool,
     *     mode: string,
     *     calendar_user_name: ?string,
     *     calendar_user_email: ?string,
     *     calendar_id: ?string,
     *     attendee_email: ?string
     * }
     */
    public function calendarSyncInfoForMember(AppointmentStaff $member): array
    {
        $calendarUser = $member->calendarUser();

        if ($calendarUser && $this->isConnected($calendarUser)) {
            return [
                'connected' => true,
                'mode' => 'linked_user',
                'calendar_user_name' => $calendarUser->name,
                'calendar_user_email' => $calendarUser->email,
                'calendar_id' => $this->calendarId($calendarUser),
                'attendee_email' => $this->attendeeEmailForMember($member, $calendarUser),
            ];
        }

        $host = $this->resolveCompanyCalendarHost($member->company_id);
        if ($host && $this->isConnected($host) && $member->email) {
            return [
                'connected' => true,
                'mode' => 'company_invite',
                'calendar_user_name' => $host->name,
                'calendar_user_email' => $host->email,
                'calendar_id' => $this->calendarId($host),
                'attendee_email' => $member->email,
            ];
        }

        return [
            'connected' => false,
            'mode' => 'none',
            'calendar_user_name' => $calendarUser?->name,
            'calendar_user_email' => $calendarUser?->email,
            'calendar_id' => null,
            'attendee_email' => $member->email ?: null,
        ];
    }

    public function calendarSyncLabelForMember(AppointmentStaff $member): string
    {
        $info = $this->calendarSyncInfoForMember($member);

        if (! $info['connected']) {
            return __('Not connected');
        }

        return $info['calendar_user_email'] ?: __('Connected');
    }

    public function attendeeEmailForMember(AppointmentStaff $member, User $calendarUser): ?string
    {
        if (! $member->email) {
            return null;
        }

        if (strcasecmp($member->email, (string) $calendarUser->email) === 0) {
            return null;
        }

        return $member->email;
    }

    /**
     * @return array{event_id: ?string, calendar_user_id: ?int, error: ?string}|null
     */
    public function createEventForAppointmentStaff(
        AppointmentStaff $member,
        Reservation $reservation,
        Source $source,
        ?User $fallbackHost = null
    ): ?array {
        $calendarUser = $member->calendarUser();
        if ($calendarUser && $this->isConnected($calendarUser)) {
            $calendarId = $this->resolvedCalendarId($calendarUser, $source->google_calendar_id);
            $result = $this->createEvent(
                $calendarUser,
                $reservation,
                $source,
                $this->attendeeEmailForMember($member, $calendarUser),
                $member->name
            );

            return $this->normalizeSyncResult($result, $calendarUser->id, $calendarId);
        }

        $host = $fallbackHost ?? $this->resolveCompanyCalendarHost($member->company_id);
        if (! $host || ! $this->isConnected($host) || ! $member->email) {
            return null;
        }

        $calendarId = $this->resolvedCalendarId($host, $source->google_calendar_id);
        $result = $this->createEventWithAttendee($host, $reservation, $source, $member->email, $member->name);

        return $this->normalizeSyncResult($result, $host->id, $calendarId);
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
            self::CALENDAR_BASE.'/calendars/'.urlencode($this->calendarIdForReservation($calendarUser, $reservation, $source)).'/events/'.urlencode($eventId),
            $payload
        );

        return $response->successful();
    }

    public function deleteEventForReservation(User $calendarUser, Reservation $reservation, ?Source $source = null): bool
    {
        $eventId = $reservation->google_event_id ?: $reservation->external_id;

        return $this->deleteEvent(
            $calendarUser,
            $eventId,
            $this->calendarIdForReservation($calendarUser, $reservation, $source)
        );
    }

    /**
     * @return array{event_id: ?string, error: ?string}
     */
    public function createEventWithAttendee(
        User $host,
        Reservation $reservation,
        Source $source,
        string $attendeeEmail,
        ?string $attendeeName = null
    ): array {
        if (! $this->isConnected($host)) {
            return ['event_id' => null, 'error' => __('Google Calendar is not connected.')];
        }

        $accessToken = $this->accessToken($host);
        if (! $accessToken) {
            return ['event_id' => null, 'error' => __('Could not refresh Google Calendar access token.')];
        }

        $contactName = $reservation->contact?->name ?? 'Customer';

        return $this->postCalendarEvent(
            $host,
            $accessToken,
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
            ],
            $reservation->id,
            'Google Calendar attendee event create failed',
            $this->resolvedCalendarId($host, $source->google_calendar_id)
        );
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

    /**
     * @return array{event_id: ?string, error: ?string}
     */
    public function createEvent(
        User $user,
        Reservation $reservation,
        Source $source,
        ?string $attendeeEmail = null,
        ?string $attendeeName = null
    ): array {
        if (! $this->isConnected($user)) {
            return ['event_id' => null, 'error' => __('Google Calendar is not connected.')];
        }

        $accessToken = $this->accessToken($user);
        if (! $accessToken) {
            return ['event_id' => null, 'error' => __('Could not refresh Google Calendar access token.')];
        }

        $contactName = $reservation->contact?->name ?? 'Customer';
        $sourceName = $source->name;
        $description = 'Reservation #'.$reservation->id;
        if ($attendeeName) {
            $description .= " with {$attendeeName}";
        }

        $payload = [
            'summary' => "{$sourceName} — {$contactName}",
            'description' => $description,
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
        ];

        if ($attendeeEmail) {
            $payload['attendees'] = [['email' => $attendeeEmail]];
        }

        $calendarId = $this->resolvedCalendarId($user, $source->google_calendar_id);

        return $this->postCalendarEvent($user, $accessToken, $payload, $reservation->id, 'Google Calendar event create failed', $calendarId);
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

    public function deleteEvent(User $user, ?string $eventId, ?string $calendarId = null): bool
    {
        if (! $eventId || ! $this->isConnected($user)) {
            return false;
        }

        $accessToken = $this->accessToken($user);
        if (! $accessToken) {
            return false;
        }

        $response = Http::withToken($accessToken)->delete(
            self::CALENDAR_BASE.'/calendars/'.urlencode($this->resolvedCalendarId($user, $calendarId)).'/events/'.urlencode($eventId)
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

    /**
     * @return array{event_id: ?string, calendar_user_id: ?int, google_calendar_id: ?string, error: ?string}
     */
    private function normalizeSyncResult(array $result, int $calendarUserId, string $calendarId): array
    {
        if (! empty($result['event_id'])) {
            return [
                'event_id' => $result['event_id'],
                'calendar_user_id' => $calendarUserId,
                'google_calendar_id' => $calendarId,
                'error' => null,
            ];
        }

        return [
            'event_id' => null,
            'calendar_user_id' => null,
            'google_calendar_id' => null,
            'error' => $result['error'] ?? __('Could not create Google Calendar event.'),
        ];
    }

    /**
     * @return array{event_id: ?string, error: ?string}
     */
    private function postCalendarEvent(User $user, string $accessToken, array $payload, int $reservationId, string $logContext, ?string $calendarId = null): array
    {
        $resolvedCalendarId = $this->resolvedCalendarId($user, $calendarId);
        $url = self::CALENDAR_BASE.'/calendars/'.urlencode($resolvedCalendarId).'/events';
        if (! empty($payload['attendees'])) {
            $url .= '?sendUpdates=all';
        }

        $response = Http::withToken($accessToken)->post($url, $payload);

        if (! $response->successful()) {
            Log::warning($logContext, [
                'reservation_id' => $reservationId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return [
                'event_id' => null,
                'error' => $response->json('error.message') ?? __('Could not create Google Calendar event.'),
            ];
        }

        return [
            'event_id' => $response->json('id'),
            'error' => null,
        ];
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
