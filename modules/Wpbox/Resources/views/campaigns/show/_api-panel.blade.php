<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">{{ __('API campaign') }}</h3>
        <div class="d-flex" style="gap: 0.5rem;">
            <a href="{{ route('wpbox.api.edit', $item) }}" class="btn btn-sm btn-primary">{{ __('Edit') }}</a>
            <a href="{{ route('wpbox.api.clone', $item) }}" class="btn btn-sm btn-outline-primary">{{ __('Clone') }}</a>
            <a href="{{ route('wpbox.api.toggle', $item) }}" class="btn btn-sm btn-outline-secondary">
                {{ $item->is_active && $item->status !== \Modules\Wpbox\Models\Campaign::STATUS_INACTIVE ? __('Deactivate') : __('Activate') }}
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p class="mb-2">
                    <strong>{{ __('Campaign ID') }}:</strong>
                    <code id="api-campaign-id">{{ $item->id }}</code>
                    <button type="button" class="btn btn-sm btn-link"
                            onclick="navigator.clipboard.writeText('{{ $item->id }}')">{{ __('Copy') }}</button>
                </p>
                <p class="mb-2">
                    <strong>{{ __('Template') }}:</strong> {{ $presenter->templateLabel() }}
                </p>
                <p class="mb-2">
                    <strong>{{ __('Status') }}:</strong> {{ $presenter->statusLabel() }}
                </p>
                <p class="mb-0">
                    <strong>{{ __('Endpoint') }}:</strong>
                    <code>{{ $presenter->sendEndpoint() }}</code>
                </p>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong>{{ __('API variable paths') }}</strong></p>
                @if (count($presenter->apiVariablePaths()) > 0)
                    <ul class="pl-3 mb-3">
                        @foreach ($presenter->apiVariablePaths() as $variable)
                            <li><code>data.{{ $variable['path'] }}</code> ({{ $variable['section'] }}.{{ $variable['id'] }})</li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted small">{{ __('No API-defined variables. Static or contact fields only.') }}</p>
                @endif

                <p class="mb-1"><strong>{{ __('Related') }}</strong></p>
                <a href="{{ route('api.info') }}" class="btn btn-sm btn-outline-primary">{{ __('API Info') }}</a>
                <a href="{{ route('campaigns.integrations') }}" class="btn btn-sm btn-outline-primary">{{ __('Integrations') }}</a>
                <a href="{{ route('wpbox.api.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('All API campaigns') }}</a>
            </div>
        </div>

        <hr>

        <h5>{{ __('Sample request') }}</h5>
        <p class="text-muted small mb-2">
            {{ __('Messages are queued by default. Pass send_now=true to attempt an immediate send. Ensure the scheduler is running for queued delivery.') }}
        </p>
        <pre class="bg-dark text-white p-3 rounded small" style="white-space: pre-wrap;">{{ $presenter->sampleCurl($apiToken ?? null) }}</pre>

        <details class="mt-2">
            <summary>{{ __('JSON payload') }}</summary>
            <pre class="bg-light p-3 rounded small mt-2">{{ json_encode($presenter->samplePayload($apiToken ?? null), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    </div>
</div>

@include('wpbox::campaigns.show._content-preview', ['contentPreview' => $contentPreview])

<div class="card shadow mb-4">
    <div class="card-header">
        <h3 class="mb-0">{{ __('Recent triggers') }}</h3>
    </div>
    <div class="card-body py-2">
        <p class="text-muted small mb-0">
            {{ __('Messages below were created when this campaign was triggered via API, integrations, or journeys.') }}
        </p>
    </div>
</div>
