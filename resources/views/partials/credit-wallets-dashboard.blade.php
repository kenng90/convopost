@if (!empty($creditWallets))
<div class="row {{ $rowClass ?? 'mt-4' }}">
    @foreach ($creditWallets as $walletIndex => $wallet)
        <div class="{{ $columnClass ?? 'col-md-3' }} mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title mb-1">{{ $wallet['label'] }}</h5>
                    <p class="small text-muted mb-3">{{ __('This billing period') }}</p>

                    @if (!empty($wallet['has_own_key']) && $wallet['key'] === 'ai')
                        <p class="mb-1 small">{{ __('Using your OpenRouter API key') }}</p>
                        <p class="mb-0 small text-muted">{{ __('Platform AI credits are not consumed.') }}</p>
                    @else
                        <div class="d-flex align-items-center">
                            @if(empty($hideCharts))
                            <div style="width: 80px; height: 80px;">
                                <canvas id="creditWalletChart{{ $walletIndex }}"></canvas>
                            </div>
                            @endif
                            <div class="{{ empty($hideCharts) ? 'ml-3' : '' }}">
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
                                    @if (($wallet['total'] ?? 0) > 0)
                                        ({{ $wallet['percent_used'] }}%)
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

@if(empty($hideCharts))
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    @foreach ($creditWallets as $walletIndex => $wallet)
        @if(empty($wallet['has_own_key']) || $wallet['key'] !== 'ai')
        (function () {
            const canvas = document.getElementById('creditWalletChart{{ $walletIndex }}');
            if (!canvas) return;
            const used = {{ (int) ($wallet['percent_used'] ?? 0) }};
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: ['{{ __('Used') }}', '{{ __('Available') }}'],
                    datasets: [{
                        data: [used, Math.max(0, 100 - used)],
                        backgroundColor: ['#ff6384', '#36a2eb']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: false } }
                }
            });
        })();
        @endif
    @endforeach
});
</script>
@endif
@endif
