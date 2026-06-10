<div class="card mt-4">
    <div class="card-header">{{ __('AI voice agent (phone)') }}</div>
    <div class="card-body">
        <p class="mb-2">{{ __('WhatsApp calls are answered by live agents in the browser. For an AI phone assistant (Twilio numbers), use Voice Call setup.') }}</p>
        @if(Route::has('voicecall.settings'))
            <a href="{{ route('voicecall.settings') }}" class="btn btn-sm btn-primary">{{ __('Open Voice Call setup') }}</a>
        @endif
    </div>
</div>
