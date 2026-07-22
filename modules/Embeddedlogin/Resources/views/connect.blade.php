<div class="card shadow max-height-vh-70 overflow-auto overflow-x-hidden">
    <div class="card-header shadow-lg">
        <b>{{ __('Connect with WhatsApp') }}</b>
    </div>

    <div class="card-body overflow-auto overflow-x-hidden scrollable-div" ref="scrollableDiv">
        @if(!$setupDone)
            <div class="alert alert-info mb-4" role="alert">
                <strong>{{ __('Before you start') }}</strong>
                <p class="mb-2 mt-2">{{ __('Have these ready. Meta will ask for them during setup:') }}</p>
                <ul class="mb-0 pl-3">
                    <li>{{ __('A Facebook account that can manage your business') }}</li>
                    <li>{{ __('A Meta Business Portfolio (Business Manager), or permission to create one') }}</li>
                    <li>{{ __('A phone number that can receive an SMS or voice call for WhatsApp verification') }}</li>
                    <li>{{ __('About 5–10 minutes uninterrupted') }}</li>
                </ul>
            </div>

            <h4 class="mb-3">{{ __('How to connect') }}</h4>
            <ol class="pl-3 mb-4">
                <li class="mb-2">
                    <strong>{{ __('Click “WhatsApp Setup” below') }}</strong>
                    <div class="text-muted small">{{ __('A Facebook / Meta window will open.') }}</div>
                </li>
                <li class="mb-2">
                    <strong>{{ __('Log in with Facebook') }}</strong>
                    <div class="text-muted small">{{ __('Allow :app to manage WhatsApp for your business when prompted.', ['app' => config('app.name')]) }}</div>
                </li>
                <li class="mb-2">
                    <strong>{{ __('Create or select your WhatsApp Business account') }}</strong>
                    <div class="text-muted small">{{ __('Choose an existing number or add a new one, then verify it with the code Meta sends.') }}</div>
                </li>
                <li class="mb-2">
                    <strong>{{ __('Finish all Meta screens until they close') }}</strong>
                    <div class="text-muted small">{{ __('Do not close the popup early. When signup succeeds, we save your connection automatically.') }}</div>
                </li>
                <li class="mb-0">
                    <strong>{{ __('Check Connection Status on the right') }}</strong>
                    <div class="text-muted small">{{ __('If it still says “Not connected”, click Refresh status. Contact support if it stays red after a successful signup.') }}</div>
                </li>
            </ol>

            <p class="text-muted small mb-4">
                {{ __('Tip: Use the same Facebook account that owns (or will own) your Meta Business Portfolio. Pop-up blockers can stop the setup window — allow pop-ups for this site if nothing opens.') }}
            </p>
        @endif

        @if (config('embeddedlogin.config_id',"")!=""&&!$setupDone)
            @include('embeddedlogin::whatsappembeded')
        @endif

        @if($setupDone)
            <div class="alert alert-success mb-4" role="alert">
                <strong>{{ __('Connected') }}</strong>
                {{ __('Your WhatsApp Business number is linked via Embedded Signup. You can reconnect below if Meta asks you to re-authorize.') }}
            </div>
            @include('embeddedlogin::whatsappembeded')
        @endif
    </div>
</div>
