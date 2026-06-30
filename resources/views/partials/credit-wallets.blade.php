@if (!empty($creditWallets))
<div class="row {{ $rowClass ?? 'mt-3' }}">
    @foreach ($creditWallets as $wallet)
        <div class="{{ $columnClass ?? 'col-md-6' }} mb-3">
            <div class="alert alert-{{ $wallet['alert'] ?? 'info' }} mb-0 h-100" role="alert">
                <h5 class="alert-heading mb-2">{{ $wallet['label'] }}</h5>
                <p class="mb-1 small text-muted">{{ __('This billing period') }}</p>

                @if (!empty($wallet['has_own_key']) && $wallet['key'] === 'ai')
                    <p class="mb-1">
                        {{ __('Using your OpenRouter API key — platform AI credits are not consumed.') }}
                    </p>
                    <p class="mb-0">
                        {{ __('Included allowance: :total', ['total' => number_format($wallet['total'])]) }}
                    </p>
                @else
                    <p class="mb-1">
                        {{ __('Available') }}:
                        <strong>{{ number_format($wallet['available']) }}</strong>
                        @if (($wallet['total'] ?? 0) > 0)
                            / {{ number_format($wallet['total']) }}
                        @endif
                    </p>
                    <p class="mb-0">
                        {{ __('Used') }}:
                        <strong>{{ number_format($wallet['used']) }}</strong>
                        ({{ $wallet['percent_used'] }}%)
                    </p>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endif
