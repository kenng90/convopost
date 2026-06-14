@extends('general.index', $setup)
@section('cardbody')
<form action="{{ $setup['action'] }}" method="POST">
    @csrf
    @isset($setup['isupdate']) @method('PUT') @endisset
    @include('partials.fields',['fiedls'=>$fields])
    <button type="submit" class="btn btn-primary">{{ isset($setup['isupdate']) ? __('Update') : __('Insert') }}</button>
</form>
@endsection
