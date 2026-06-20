<div class="card shadow max-height-vh-70 overflow-auto overflow-x-hidden">
    <div class="card-header shadow-lg">
        <b>{{ __('Connect with Meta') }}</b>
    </div>

    <div class="card-body overflow-auto overflow-x-hidden scrollable-div" ref="scrollableDiv">
        @if (config('embeddedlogin.config_id', '') != '')
            @include('embeddedlogin::whatsappembeded', [
                'setupDone' => $setupDone ?? false,
                'signupOptions' => $signupOptions ?? [],
            ])
        @else
            <div class="alert alert-warning mb-0">
                {{ __('Embedded Signup is not configured. Set EMBEDDED_FB_CONFIG_ID in site settings.') }}
            </div>
        @endif

        @if($setupDone ?? false)
            <div class="alert alert-success mt-3 mb-0">
                {{ __('WhatsApp is connected. Use the buttons above to re-connect or add Instagram and Messenger.') }}
            </div>
        @endif
    </div>
</div>
