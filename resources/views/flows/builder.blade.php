@extends('layouts.app')

@section('head')
@vite(['resources/css/app.css', 'resources/js/app.js'])
@endsection

@section('content')
<div class="container-fluid" style="padding: 0;">
    <livewire:flows-builder />
</div>
@endsection
