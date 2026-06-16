@extends('general.index', $setup)
@section('cardbody')
@if(isset($calendarConnected))
    <div class="alert alert-{{ $calendarConnected ? 'success' : 'warning' }} mb-4">
        {{ $calendarConnected ? __('Google Calendar is connected for the linked user.') : __('No Google Calendar connected — bookings will send a calendar invite to the team member email when the company calendar is connected.') }}
    </div>
@endif
<form action="{{ $setup['action'] }}" method="POST">
    @csrf
    @isset($setup['isupdate']) @method('PUT') @endisset
    @include('partials.fields',['fiedls'=>$fields])
    <button type="submit" class="btn btn-primary">{{ isset($setup['isupdate']) ? __('Update') : __('Insert') }}</button>
</form>
@endsection
