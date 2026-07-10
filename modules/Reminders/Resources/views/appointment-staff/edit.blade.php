@extends('general.index', $setup)
@section('cardbody')
@if(isset($calendarSyncInfo))
    @if ($calendarSyncInfo['connected'])
        <div class="alert alert-success mb-4">
            <strong>{{ __('Google Calendar connected') }}</strong>
            <ul class="mb-0 mt-2 pl-3">
                <li>{{ __('Events are created on') }} <strong>{{ $calendarSyncInfo['calendar_user_name'] }}</strong> ({{ $calendarSyncInfo['calendar_user_email'] }})</li>
                <li>{{ __('Calendar ID') }}: <code>{{ $calendarSyncInfo['calendar_id'] }}</code></li>
                @if ($calendarSyncInfo['attendee_email'])
                    <li>{{ __('Team member email :email will also receive a Google Calendar invite.', ['email' => $calendarSyncInfo['attendee_email']]) }}</li>
                @endif
            </ul>
            @if ($calendarSyncInfo['mode'] === 'company_invite')
                <small class="d-block mt-2 text-muted">{{ __('The linked platform user has no Google Calendar connected, so the company calendar is used and the team member is invited as an attendee.') }}</small>
            @endif
        </div>
    @else
        <div class="alert alert-warning mb-4">
            <strong>{{ __('Google Calendar not connected for bookings') }}</strong>
            @if ($calendarSyncInfo['calendar_user_name'])
                <p class="mb-1 mt-2">{{ __('Linked platform user :name (:email) does not have Google Calendar connected.', ['name' => $calendarSyncInfo['calendar_user_name'], 'email' => $calendarSyncInfo['calendar_user_email']]) }}</p>
            @endif
            <p class="mb-0">{{ __('Connect Google Calendar in booking settings, or link this team member to a platform user who has connected their calendar.') }}</p>
        </div>
    @endif
@endif
<form action="{{ $setup['action'] }}" method="POST">
    @csrf
    @isset($setup['isupdate']) @method('PUT') @endisset
    @include('partials.fields',['fiedls'=>$fields])
    <button type="submit" class="btn btn-primary">{{ isset($setup['isupdate']) ? __('Update') : __('Insert') }}</button>
</form>
@endsection
