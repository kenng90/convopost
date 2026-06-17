@extends('layouts.app')

@section('admin_title')
    {{ __('Flow templates') }}
@endsection

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">{{ __('Flow templates library') }}</h2>
                    <p class="text-muted mb-0">{{ __('Install curated automations with copy and variables ready to customize.') }}</p>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <h4 class="mb-3">{{ __('AI Flow Assistant') }}</h4>
                    <p class="text-muted small">{{ __('Describe what you want in plain language — we draft a flow you can review and publish.') }}</p>
                    <form id="ai-flow-form" class="form-inline flex-wrap gap-2">
                        @csrf
                        <input type="text" name="description" class="form-control flex-grow-1 mb-2" style="min-width: 280px;" placeholder="{{ __('When someone says pay, send M-Pesa STK and confirm...') }}" required minlength="10">
                        <input type="text" name="name" class="form-control mb-2" placeholder="{{ __('Flow name (optional)') }}">
                        <button type="submit" class="btn btn-primary mb-2">{{ __('Generate draft') }}</button>
                    </form>
                    <div id="ai-flow-result" class="small text-muted mt-2"></div>
                </div>
            </div>

            @foreach($grouped as $category => $templates)
                <h3 class="text-capitalize mt-4 mb-3">{{ str_replace('_', ' ', $category) }}</h3>
                <div class="row">
                    @foreach($templates as $template)
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body d-flex flex-column">
                                    <h4 class="card-title">{{ $template['name'] }}</h4>
                                    <p class="card-text text-muted flex-grow-1">{{ $template['description'] }}</p>
                                    @if(!empty($template['setup_hint']))
                                        <p class="small text-info">{{ $template['setup_hint'] }}</p>
                                    @endif
                                    <form method="POST" action="{{ route('flow-templates.install', $template['key']) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Install template') }}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
document.getElementById('ai-flow-form')?.addEventListener('submit', function (e) {
    e.preventDefault();
    const form = e.target;
    const result = document.getElementById('ai-flow-result');
    result.textContent = '{{ __("Generating...") }}';
    fetch('{{ route("flow-templates.generate") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': form.querySelector('[name=_token]').value,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            description: form.description.value,
            name: form.name.value,
        }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            result.innerHTML = data.summary + ' <a href="' + data.edit_url + '">{{ __("Open editor") }}</a>';
        } else {
            result.textContent = data.message || '{{ __("Generation failed") }}';
        }
    })
    .catch(() => result.textContent = '{{ __("Generation failed") }}');
});
</script>
@endsection
