<div class="card shadow sticky-top" style="top: 1rem;">
    <div class="card-header"><h3 class="mb-0">{{ __('Preview') }}</h3></div>
    <div class="card-body">
        @if ($channel === \Modules\Wpbox\Models\Campaign::CHANNEL_WHATSAPP && $selectedTemplate && $templateComponents)
            <div style="background: url('{{ asset('default/wpbox/bg.png') }}'); padding: 1rem; border-radius: 8px;">
                <div class="card" style="border-top-left-radius: 0;">
                    @foreach ($templateComponents as $component)
                        @if ($component['type'] === 'HEADER' && ($component['format'] ?? '') === 'IMAGE')
                            @if ($imageupload)
                                <img src="{{ $imageupload->temporaryUrl() }}" class="card-img-top" alt="">
                            @endif
                        @endif
                    @endforeach
                    <div class="card-body">
                        @foreach ($templateComponents as $component)
                            @if ($component['type'] === 'HEADER' && ($component['format'] ?? '') === 'TEXT')
                                <h5 class="card-title">{{ $this->previewHeaderText($component['text'] ?? '') }}</h5>
                            @elseif ($component['type'] === 'BODY')
                                <pre class="card-text mb-0" style="font-family: inherit; white-space: pre-wrap; background: none; border: none;">{{ $this->previewBodyText($component['text'] ?? '') }}</pre>
                            @elseif ($component['type'] === 'FOOTER')
                                <small class="text-muted">{{ $component['text'] ?? '' }}</small>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @elseif ($channel === \Modules\Wpbox\Models\Campaign::CHANNEL_SMS)
            <div class="p-3 rounded" style="background: #e9ecef;">
                <div class="bg-white rounded p-3 shadow-sm" style="max-width: 280px;">
                    {{ $smsBody ?: __('Your SMS will appear here...') }}
                </div>
            </div>
        @elseif ($channel === \Modules\Wpbox\Models\Campaign::CHANNEL_EMAIL)
            <div class="border rounded p-3 bg-white">
                <strong>{{ $emailSubject ?: __('Subject line') }}</strong>
                <hr class="my-2">
                <div style="white-space: pre-wrap;">{{ $emailBody ?: __('Email body...') }}</div>
            </div>
        @else
            <p class="text-muted mb-0">{{ __('Select a template to preview.') }}</p>
        @endif
    </div>
</div>
