<div class="card shadow mb-4">
    <div class="card-header">
        <h3 class="mb-0">{{ __('Message content') }}</h3>
    </div>
    <div class="card-body">
        @if (($contentPreview['channel'] ?? '') === \Modules\Wpbox\Models\Campaign::CHANNEL_WHATSAPP)
            @if (! empty($contentPreview['components']))
                <div style="background: url('{{ asset('default/wpbox/bg.png') }}'); padding: 1rem; border-radius: 8px; max-width: 420px;">
                    <div class="card" style="border-top-left-radius: 0;">
                        <div class="card-body">
                            @foreach ($contentPreview['components'] as $component)
                                @if ($component['type'] === 'HEADER' && ($component['format'] ?? '') === 'TEXT')
                                    <h5 class="card-title">{{ $component['text'] ?? '' }}</h5>
                                @elseif ($component['type'] === 'BODY')
                                    <pre class="card-text mb-0" style="font-family: inherit; white-space: pre-wrap; background: none; border: none;">{{ $component['text'] ?? '' }}</pre>
                                @elseif ($component['type'] === 'FOOTER')
                                    <small class="text-muted">{{ $component['text'] ?? '' }}</small>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <p class="text-muted mb-0">{{ __('No template content available.') }}</p>
            @endif
        @elseif (($contentPreview['channel'] ?? '') === \Modules\Wpbox\Models\Campaign::CHANNEL_SMS)
            <div class="p-3 rounded" style="background: #e9ecef; max-width: 420px;">
                <div class="bg-white rounded p-3 shadow-sm">
                    {{ $contentPreview['body'] ?: __('No SMS content saved.') }}
                </div>
            </div>
        @elseif (($contentPreview['channel'] ?? '') === \Modules\Wpbox\Models\Campaign::CHANNEL_EMAIL)
            <div class="border rounded p-3 bg-white" style="max-width: 560px;">
                <strong>{{ $contentPreview['subject'] ?: __('Subject line') }}</strong>
                <hr class="my-2">
                <div style="white-space: pre-wrap;">{{ $contentPreview['body'] ?: __('No email body saved.') }}</div>
            </div>
        @endif
    </div>
</div>
