@if (config('wpbox.one_signal_app_id') && config('wpbox.one_signal_app_id', '') != '')
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
    <script>
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        OneSignalDeferred.push(async function (OneSignal) {
            try {
                await OneSignal.init({
                    allowLocalhostAsSecureOrigin: true,
                    appId: @json(config('wpbox.one_signal_app_id', '')),
                });

                var userId = @json((string) auth()->id());
                if (userId) {
                    await OneSignal.login(userId);
                }
            } catch (error) {
                console.warn('[OneSignal] Skipped — configure Web Push for this app in the OneSignal dashboard, or clear ONE_SIGNAL_APP_ID in .env.', error);
            }
        });
    </script>
@endif
