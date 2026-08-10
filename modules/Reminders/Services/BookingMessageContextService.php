<?php

namespace Modules\Reminders\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Remineder;
use Modules\Reminders\Models\Reservation;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Message;

class BookingMessageContextService
{
    public const FIELD_CONTACT_NAME = -1;

    public const FIELD_START_DATE = -4;

    public const FIELD_START_TIME = -5;

    public const FIELD_START_DATE_TIME = -6;

    public const FIELD_END_DATE = -7;

    public const FIELD_END_TIME = -8;

    public const FIELD_END_DATE_TIME = -9;

    public const FIELD_EXTERNAL_ID = -10;

    public const FIELD_LOCATION = -11;

    public const FIELD_SERVICE_NAME = -12;

    public const FIELD_STAFF_NAME = -13;

    public const FIELD_EVENT_TITLE = -14;

    /**
     * Match IDs available when creating reminder/confirmation campaigns.
     *
     * @return array<int, string>
     */
    public static function campaignFieldOptions(): array
    {
        return [
            self::FIELD_START_DATE => __('Date (start)'),
            self::FIELD_START_TIME => __('Time (start)'),
            self::FIELD_START_DATE_TIME => __('Date and time (start)'),
            self::FIELD_END_DATE => __('Date (end)'),
            self::FIELD_END_TIME => __('Time (end)'),
            self::FIELD_END_DATE_TIME => __('Date and time (end)'),
            self::FIELD_EXTERNAL_ID => __('Booking reference'),
            self::FIELD_LOCATION => __('Location'),
            self::FIELD_SERVICE_NAME => __('Service name'),
            self::FIELD_STAFF_NAME => __('Staff / host name'),
            self::FIELD_EVENT_TITLE => __('Event title'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function fieldPathMap(): array
    {
        return [
            self::FIELD_START_DATE => 'start_date',
            self::FIELD_START_TIME => 'start_time',
            self::FIELD_START_DATE_TIME => 'start_date_time',
            self::FIELD_END_DATE => 'end_date',
            self::FIELD_END_TIME => 'end_time',
            self::FIELD_END_DATE_TIME => 'end_date_time',
            self::FIELD_EXTERNAL_ID => 'external_id',
            self::FIELD_LOCATION => 'location',
            self::FIELD_SERVICE_NAME => 'service_name',
            self::FIELD_STAFF_NAME => 'staff_name',
            self::FIELD_EVENT_TITLE => 'event_title',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function forReservation(Reservation $reservation): array
    {
        $reservation->loadMissing(['source', 'appointmentStaffMember', 'contact']);

        $timezone = $reservation->source?->timezone ?: config('app.timezone', 'UTC');
        $start = $this->inTimezone($reservation->start_date, $timezone);
        $end = $this->inTimezone($reservation->end_date, $timezone);

        return $this->buildContext(
            start: $start,
            end: $end,
            externalId: $this->displayReference($reservation->external_id, $reservation->id),
            location: $reservation->source?->location,
            serviceName: $reservation->source?->name,
            staffName: $reservation->appointmentStaffMember?->name,
            eventTitle: null,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function forEventRegistration(EventRegistration $registration): array
    {
        $registration->loadMissing(['event', 'occurrence', 'contact']);

        $timezone = $registration->event?->timezone ?: config('app.timezone', 'UTC');
        $start = $this->inTimezone($registration->occurrence?->starts_at, $timezone);
        $end = $this->inTimezone($registration->occurrence?->ends_at, $timezone);

        return $this->buildContext(
            start: $start,
            end: $end,
            externalId: $this->displayReference($registration->external_id, $registration->id),
            location: $registration->event?->location,
            serviceName: $registration->event?->title,
            staffName: $registration->event?->host?->name,
            eventTitle: $registration->event?->title,
        );
    }

    public function sendReservationConfirmation(Reservation $reservation): ?Message
    {
        $reservation->loadMissing(['source', 'contact']);
        $campaignId = $reservation->source?->confirmation_campaign_id;

        if (! $campaignId || ! $reservation->contact) {
            return null;
        }

        return $this->dispatchCampaign(
            campaignId: (int) $campaignId,
            contact: $reservation->contact,
            extraValue: $this->forReservation($reservation),
            sendAt: now(),
            messageExtra: (string) $reservation->id,
        );
    }

    public function sendEventConfirmation(EventRegistration $registration): ?Message
    {
        $registration->loadMissing(['event', 'contact']);
        $campaignId = $registration->event?->confirmation_campaign_id;

        if (! $campaignId || ! $registration->contact) {
            return null;
        }

        return $this->dispatchCampaign(
            campaignId: (int) $campaignId,
            contact: $registration->contact,
            extraValue: $this->forEventRegistration($registration),
            sendAt: now(),
            messageExtra: 'event_reg:'.$registration->id,
        );
    }

    public function sendReservationReminder(Remineder $reminder, Reservation $reservation): ?Message
    {
        $reservation->loadMissing(['contact', 'source']);

        if (! $reservation->contact || ! $reminder->campaign_id) {
            return null;
        }

        $timezone = $reservation->source?->timezone ?: config('app.timezone', 'UTC');
        $start = $this->inTimezone($reservation->start_date, $timezone);
        $end = $this->inTimezone($reservation->end_date, $timezone);
        $sendAt = $this->resolveSendAt($reminder, $start, $end);

        if (! $sendAt) {
            return null;
        }

        return $this->dispatchCampaign(
            campaignId: (int) $reminder->campaign_id,
            contact: $reservation->contact,
            extraValue: $this->forReservation($reservation),
            sendAt: $sendAt,
            messageExtra: (string) $reservation->id,
        );
    }

    public function sendEventReminder(Remineder $reminder, EventRegistration $registration): ?Message
    {
        $registration->loadMissing(['contact', 'event', 'occurrence']);

        if (! $registration->contact || ! $reminder->campaign_id) {
            return null;
        }

        $timezone = $registration->event?->timezone ?: config('app.timezone', 'UTC');
        $start = $this->inTimezone($registration->occurrence?->starts_at, $timezone);
        $end = $this->inTimezone($registration->occurrence?->ends_at, $timezone);
        $sendAt = $this->resolveSendAt($reminder, $start, $end);

        if (! $sendAt) {
            return null;
        }

        return $this->dispatchCampaign(
            campaignId: (int) $reminder->campaign_id,
            contact: $registration->contact,
            extraValue: $this->forEventRegistration($registration),
            sendAt: $sendAt,
            messageExtra: 'event_reg:'.$registration->id,
        );
    }

    /**
     * @param  array<string, string|null>  $extraValue
     * @return array{variables: array<string, mixed>, variables_match: array<string, mixed>}
     */
    public function applyFieldMappings(Campaign $campaign, array $extraValue): array
    {
        $variables = json_decode($campaign->variables ?: '{}', true) ?: [];
        $variablesMatch = json_decode($campaign->variables_match ?: '{}', true) ?: [];
        $pathMap = self::fieldPathMap();

        foreach ($variablesMatch as $section => $matches) {
            if (! is_array($matches)) {
                continue;
            }

            foreach ($matches as $matchKey => $matchValue) {
                if (is_array($matchValue)) {
                    foreach ($matchValue as $nestedKey => $nestedValue) {
                        $this->mapMatchValue(
                            $variables,
                            $variablesMatch,
                            $pathMap,
                            $section,
                            $matchKey,
                            $nestedKey,
                            $nestedValue,
                            true
                        );
                    }

                    continue;
                }

                $this->mapMatchValue(
                    $variables,
                    $variablesMatch,
                    $pathMap,
                    $section,
                    $matchKey,
                    null,
                    $matchValue,
                    false
                );
            }
        }

        return [
            'variables' => $variables,
            'variables_match' => $variablesMatch,
        ];
    }

    private function mapMatchValue(
        array &$variables,
        array &$variablesMatch,
        array $pathMap,
        string $section,
        int|string $matchKey,
        int|string|null $nestedKey,
        mixed $matchValue,
        bool $nested,
    ): void {
        $matchValueInt = (int) $matchValue;

        if ($matchValueInt >= -3 || ! isset($pathMap[$matchValueInt])) {
            return;
        }

        $path = $pathMap[$matchValueInt];

        if ($nested) {
            $variables[$section][$matchKey][$nestedKey] = $path;
            $variablesMatch[$section][$matchKey][$nestedKey] = '-3';

            return;
        }

        $variables[$section][$matchKey] = $path;
        $variablesMatch[$section][$matchKey] = '-3';
    }

    /**
     * @param  array<string, string|null>  $extraValue
     */
    private function dispatchCampaign(
        int $campaignId,
        $contact,
        array $extraValue,
        Carbon $sendAt,
        string $messageExtra,
    ): ?Message {
        $campaign = Campaign::find($campaignId);

        if (! $campaign) {
            return null;
        }

        $mapped = $this->applyFieldMappings($campaign, $extraValue);

        // Mutate only this in-memory instance; never persist mapping rewrites.
        $campaign->variables = json_encode($mapped['variables']);
        $campaign->variables_match = json_encode($mapped['variables_match']);

        $contact->extra_value = $extraValue;

        $request = new Request();
        $request->replace([
            'send_time' => $sendAt->toDateTimeString(),
        ]);

        $message = $campaign->makeMessages($request, $contact);

        if (! $message) {
            return null;
        }

        try {
            $message->extra = $messageExtra;
            $message->save();
        } catch (\Throwable) {
        }

        return $message;
    }

    private function resolveSendAt(Remineder $reminder, ?Carbon $start, ?Carbon $end): ?Carbon
    {
        if (! $start || ! $end) {
            return null;
        }

        $minutes = $this->offsetInMinutes($reminder);

        if ((int) $reminder->type === 1) {
            return $start->copy()->subMinutes($minutes);
        }

        return $end->copy()->addMinutes($minutes);
    }

    private function offsetInMinutes(Remineder $reminder): int
    {
        $time = (int) $reminder->time;

        return match ($reminder->time_type) {
            'hours' => $time * 60,
            'days' => $time * 24 * 60,
            'weeks' => $time * 24 * 60 * 7,
            'months' => $time * 24 * 60 * 30,
            default => $time,
        };
    }

    private function displayReference(?string $externalId, int|string|null $id): string
    {
        $externalId = trim((string) $externalId);

        if ($externalId !== '') {
            return $externalId;
        }

        return '#'.(string) $id;
    }

    private function inTimezone(mixed $value, string $timezone): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->timezone($timezone);
        }

        return Carbon::parse((string) $value, config('app.timezone', 'UTC'))->timezone($timezone);
    }

    /**
     * @return array<string, string|null>
     */
    private function buildContext(
        ?Carbon $start,
        ?Carbon $end,
        ?string $externalId,
        ?string $location,
        ?string $serviceName,
        ?string $staffName,
        ?string $eventTitle,
    ): array {
        return [
            'start_date' => $start?->format('M j, Y'),
            'start_time' => $start?->format('g:i A'),
            'start_date_time' => $start?->format('M j, Y g:i A'),
            'end_date' => $end?->format('M j, Y'),
            'end_time' => $end?->format('g:i A'),
            'end_date_time' => $end?->format('M j, Y g:i A'),
            'external_id' => $externalId,
            'location' => $location ?: null,
            'service_name' => $serviceName,
            'staff_name' => $staffName,
            'event_title' => $eventTitle,
        ];
    }
}
