@extends('layouts.app', ['title' => __('Send new campaign')])

@section('content')
@include('companies.partials.modals')

<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            @if ($isBot)
                <h1 class="mb-3 mt--3">🤖 {{__('Create new template bot')}}</h1>
            @elseif ($isAPI)
                <h1 class="mb-3 mt--3">🔌 {{__('Create new API campaign')}}</h1>
            @elseif ($isReminder)
                <h1 class="mb-3 mt--3">⏰ {{__('Create new reminder')}}</h1>
            @else
                <h1 class="mb-3 mt--3">📢 {{__('Send new campaign')}}</h1>
            @endif
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
    @include('wpbox::campaigns.new.broadcast_type')
</div>
@endsection

@section('scripts')
<script>
    function selectBroadcastType(type) {
        let url = "{{ route('campaigns.create') }}/" + type;
        
        @if(request()->has('type'))
            url += "?type={{ request('type') }}";
        @endif
        
        window.location.href = url;
    }
</script>
@endsection