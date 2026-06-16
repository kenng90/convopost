@extends('general.index', $setup)
@section('cardbody')
<form action="{{ $setup['action'] }}" method="POST">
    @csrf
    @isset($setup['isupdate']) @method('PUT') @endisset
    @include('partials.fields',['fields'=>$fields])
    @isset($closures)
        @include('reminders::departments.partials.closures', ['closures' => $closures])
    @endisset
    <button type="submit" class="btn btn-primary">{{ isset($setup['isupdate']) ? __('Update') : __('Insert') }}</button>
</form>
@endsection
