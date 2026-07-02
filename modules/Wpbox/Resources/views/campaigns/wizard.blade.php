@extends('layouts.app', ['title' => __('Campaign wizard')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <h1 class="mb-3 mt--3">📢 {{ __('Send new campaign') }}</h1>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
    @livewire('campaign-wizard')
</div>
@endsection
