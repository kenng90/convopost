<?php

namespace Modules\Reminders\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Modules\Reminders\Services\GoogleCalendarService;

class GoogleCalendarController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService
    ) {
    }

    public function connect()
    {
        $this->ownerAndStaffOnly();

        return Socialite::driver('google')
            ->scopes([
                'https://www.googleapis.com/auth/calendar',
                'https://www.googleapis.com/auth/calendar.events',
            ])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
            ])
            ->redirectUrl($this->calendarRedirectUri())
            ->redirect();
    }

    public function callback(Request $request)
    {
        $this->ownerAndStaffOnly();

        if ($request->has('error')) {
            return redirect()
                ->route('reminders.booking-settings.index')
                ->withStatus(__('Google Calendar connection was cancelled.'));
        }

        $googleUser = Socialite::driver('google')
            ->redirectUrl($this->calendarRedirectUri())
            ->user();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->googleCalendarService->storeTokens(
            $user,
            $googleUser->token,
            $googleUser->refreshToken,
            $googleUser->expiresIn
        );

        if (! $user->getConfig('google_calendar_id')) {
            $user->setConfig('google_calendar_id', 'primary');
        }

        return redirect()
            ->route('reminders.booking-settings.index')
            ->withStatus(__('Google Calendar connected successfully.'));
    }

    public function disconnect()
    {
        $this->ownerAndStaffOnly();

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->googleCalendarService->disconnect($user);

        return redirect()
            ->route('reminders.booking-settings.index')
            ->withStatus(__('Google Calendar disconnected.'));
    }

    /**
     * Must exactly match an Authorized redirect URI in Google Cloud Console.
     */
    private function calendarRedirectUri(): string
    {
        return route('reminders.google.callback', [], true);
    }
}
