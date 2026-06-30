@extends('general.index', $setup)
@section('cardbody')
<form action="{{ $setup['action'] }}" method="POST" enctype="multipart/form-data">
    @csrf
    @isset($setup['isupdate'])
        @method('PUT')
    @endisset
    @isset($setup['inrow'])
        <div class="row">
    @endisset
        @include('partials.fields',['fiedls'=>$fields])
    @isset($setup['inrow'])
        </div>
    @endisset
    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
</form>

@include('reminders::events.partials.occurrence-form', [
    'title' => __('Add another session'),
    'subtitle' => __('Each session is a specific date and time guests can register for.'),
    'action' => route('reminders.events.occurrences.store', ['event' => $event->id]),
    'submitLabel' => __('Add session'),
    'tableOccurrences' => $event->occurrences,
    'eventId' => $event->id,
    'eventTimezone' => $event->timezone ?: config('app.timezone', 'UTC'),
])
@endsection
