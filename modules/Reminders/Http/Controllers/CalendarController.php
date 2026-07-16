<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\GoogleCalendarService;

class CalendarController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
    ) {
    }

    public function index()
    {
        $this->ownerAndStaffOnly();
        $company = $this->getCompany();
        abort_unless($company, 403);

        $calendarUser = $this->resolveCalendarUser($company);
        $timezone = $this->resolveTimezone($company);
        $connected = $calendarUser && $this->googleCalendarService->isConnected($calendarUser);
        $calendars = $connected
            ? $this->embedCalendarsFor($company, $calendarUser)
            : [];
        $defaultCalendarId = $connected
            ? ($this->googleCalendarService->calendarId($calendarUser) ?: ($calendars[0]['id'] ?? null))
            : null;

        if ($defaultCalendarId && ! collect($calendars)->contains(fn (array $calendar) => $calendar['id'] === $defaultCalendarId)) {
            $defaultCalendarId = $calendars[0]['id'] ?? null;
        }

        return view('reminders::calendar.index', [
            'setup' => [
                'title' => __('Calendar'),
                'subtitle' => __('Switch between Google calendars connected to your booking account.'),
                'action_link' => route('reminders.booking-settings.index'),
                'action_name' => __('Booking settings'),
                'iscontent' => true,
            ],
            'connected' => $connected,
            'calendars' => $calendars,
            'defaultCalendarId' => $defaultCalendarId,
            'timezone' => $timezone,
            'embedUrl' => $defaultCalendarId
                ? $this->googleCalendarService->embedUrl($defaultCalendarId, $timezone)
                : null,
            'connectUrl' => route('reminders.google.connect'),
            'settingsUrl' => route('reminders.booking-settings.index'),
        ]);
    }

    /**
     * @return list<array{id: string, label: string, group: string, embed_url: string}>
     */
    private function embedCalendarsFor(Company $company, User $user): array
    {
        $timezone = $this->resolveTimezone($company);
        $calendars = collect($this->googleCalendarService->listCalendars($user, 'reader'))
            ->map(fn (array $calendar) => [
                'id' => $calendar['id'],
                'label' => $calendar['label'] ?? $calendar['summary'] ?? $calendar['id'],
                'group' => __('Google calendars'),
                'embed_url' => $this->googleCalendarService->embedUrl($calendar['id'], $timezone),
            ]);

        $serviceCalendars = Source::queryForCompany($company->id)
            ->whereNotNull('google_calendar_id')
            ->where('google_calendar_id', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'google_calendar_id'])
            ->map(fn (Source $source) => [
                'id' => $source->google_calendar_id,
                'label' => $source->name.' ('.__('Service').')',
                'group' => __('Services'),
                'embed_url' => $this->googleCalendarService->embedUrl($source->google_calendar_id, $timezone),
            ]);

        return $calendars
            ->concat($serviceCalendars)
            ->unique('id')
            ->values()
            ->all();
    }

    private function resolveCalendarUser(Company $company): ?User
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->hasRole('owner') || $user->isOrganizationManager()) {
            return $user;
        }

        $member = AppointmentStaff::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->with('user')
            ->first();

        abort_unless($member, 403, __('Your account is not linked to a booking team member.'));

        return $member->user ?: $user;
    }

    private function resolveTimezone(Company $company): string
    {
        $fromSource = Source::queryForCompany($company->id)
            ->whereNotNull('timezone')
            ->where('timezone', '!=', '')
            ->orderBy('id')
            ->value('timezone');

        return $fromSource ?: config('app.timezone', 'UTC');
    }
}
