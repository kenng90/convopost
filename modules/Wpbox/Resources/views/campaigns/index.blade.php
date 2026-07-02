@extends('general.index', $setup)

@section('cardbody')
    @if (isset($whatsappReady) && ! $whatsappReady)
        <div class="alert alert-warning">
            {{ __('WhatsApp is not fully connected. You can draft campaigns, but messages will not send until setup is complete.') }}
            <a href="{{ route('whatsapp.setup') }}" class="alert-link">{{ __('Complete setup') }}</a>
        </div>
    @endif

    @if (!empty($dispatcherLastRun))
        <p class="text-muted small mb-3">{{ __('Dispatcher last run') }}: {{ $dispatcherLastRun }}</p>
    @else
        <p class="text-warning small mb-3">{{ __('Campaign dispatcher has not run yet. Scheduled messages are processed every minute by the server scheduler.') }}</p>
    @endif

    <form method="GET" class="row mb-4">
        <div class="col-md-3">
            <input type="text" name="name" value="{{ request('name') }}" class="form-control" placeholder="{{ __('Search name') }}">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-control">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (['draft','scheduled','sending','completed','paused','cancelled','paused_insufficient_credits'] as $status)
                    <option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst(str_replace('_',' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="broadcast_type" class="form-control">
                <option value="">{{ __('All types') }}</option>
                @foreach (['group','file','quick'] as $type)
                    <option value="{{ $type }}" @selected(request('broadcast_type')===$type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary" type="submit">{{ __('Filter') }}</button>
        </div>
    </form>

    @foreach ($setup['items'] as $item)
        <a href="{{ route('campaigns.show', $item->id) }}">
            <h3 class="mb-0">{{ __('Campaign') }}: {{ $item->name }}</h3>
            @if (! $item->template)
                <p class="text-warning small mb-2">{{ __('Template unavailable') }}</p>
            @endif
            <br />
            @include('wpbox::campaigns.infoboxes', ['item' => $item])
            <hr />
        </a>
    @endforeach
    @if (count($setup['items'])==0)
        <div style="display: flex; justify-content: center; width:100%;">
            <div class="text-center">
                <div class="mb-4">
                    <dotlottie-player src="https://lottie.host/ff90657b-c74a-4325-9ac9-639e01d1e9de/F9NKBIxQ9k.lottie" background="transparent" speed="1" style="width: 300px; height: 300px; opacity: 0.6" loop autoplay></dotlottie-player>
                </div>
                <div class="mb-4">
                    <h4 class="text-muted">{{ __('There are no campaigns, send your first one!')}}</h4>
                </div>
                <div>
                    <a href="{{ route('campaigns.wizard') }}" class="btn btn-lg btn-primary">
                        <i class="fas fa-plus-circle mr-2"></i>{{__('Create your first campaign')}}
                    </a>
                </div>
            </div>
           
        </div>
    @endif
@endsection
@section('js')
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
@endsection