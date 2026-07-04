@extends('layouts.app', ['title' => __('Whatsapp API')])
@section('content')
<div class="header pb-8 pt-2 pt-md-7">
    <div class="container-fluid">
        <div class="header-body">
            <h1 class="mb-3 mt--3">🔗 {{__('API Info')}}</h1>
            <div class="row align-items-center pt-2">
            </div>
        </div>
    </div>
</div>
<div class="container-fluid mt--8">  
    <div class="row">
        <div class="col-12">
            @include('partials.flash')

            <div class="card shadow max-height-vh-70 overflow-auto overflow-x-hidden">
                <div class="card-header shadow-lg">
                    <b>{{ __('API endpoint') }}</b>
                </div>
                <div class="card-body overflow-auto overflow-x-hidden scrollable-div" ref="scrollableDiv" >
                    {{config('app.url')}}
                </div>
            </div>  
            <br />
            <div class="card shadow max-height-vh-70 overflow-auto overflow-x-hidden">
                <div class="card-header shadow-lg">
                    <b>{{ __('You API token') }}</b>
                </div>
                <div class="card-body overflow-auto overflow-x-hidden scrollable-div" ref="scrollableDiv" >
                    {{$token}}
                </div>
            </div>  


            

            <br />
            <div class="card shadow max-height-vh-70 overflow-auto overflow-x-hidden">
                <div class="card-header shadow-lg">
                    <b>{{ __('Send API campaign') }}</b>
                </div>
                <div class="card-body">
                    <p class="mb-2">{{ __('Trigger a saved API campaign by ID. Messages are queued unless send_now is true. Ensure the scheduler is running.') }}</p>
                    <code>POST {{ rtrim(config('app.url'), '/') }}/api/wpbox/sendcampaigns</code>
                    <pre class="bg-light p-3 mt-3 mb-0 small">token, campaign_id, phone
data[order][id]=1001   (optional API variable paths)
send_now=true          (optional immediate send)</pre>
                </div>
            </div>

            <br />
            <div class="card shadow max-height-vh-70 overflow-auto overflow-x-hidden">
                <div class="card-footer">
                    <a href="{{ route('wpbox.api.index') }}" class="btn btn-success">🔗 {{ __('List of API campaigns') }}</a>
                    <a href="{{ route('wpbox.api.create') }}" class="btn btn-primary">🔌 {{ __('New API campaign') }}</a>
                    <a href="{{ config('wpbox.api_docs','https://documenter.getpostman.com/view/8538142/2s9Ykn8gvj') }}" target="_blank" class="btn btn-outline-primary">🔗 {{ __('Documentation') }}</a>
                </div>
            </div>
        </div>  
    </div>
</div>
@endsection
