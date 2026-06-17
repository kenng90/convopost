@include('platform::partials.health-alerts')

@if(isset($platform) && count($platform) > 0)
<div class="row mt-4">
    <div class="col-12">
        <h3 class="mb-3">{{ __('WhatsApp Revenue') }}</h3>
    </div>
    @include('partials.infoboxes.advanced', ['collection' => $platform])
</div>
@endif
