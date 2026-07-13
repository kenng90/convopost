@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Name') }}</th>
    <th>{{ __('Priority') }}</th>
    <th>{{ __('Status') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
    @foreach ($setup['items'] as $item)
        <tr>
            <td>
                {{ $item->name }}
                @if(!empty($item->exclusive_on_match))
                    <span class="badge badge-info">{{ __('Exclusive') }}</span>
                @endif
            </td>
            <td>{{ $item->priority ?? 0 }}</td>
            <td>
                @if(($item->is_active ?? true))
                    <span class="badge badge-success">{{ __('Active') }}</span>
                @else
                    <span class="badge badge-secondary">{{ __('Paused') }}</span>
                @endif
            </td>
            <td>
                <a href="{{ route('flowmaker.edit',['flow'=>$item->id]) }}" class="btn btn-success btn-sm">
                    <i class="ni ni-ruler-pencil"></i> {{ __('Flow maker')}}
                </a>
                <a href="{{ route('flows.edit',['flow'=>$item->id]) }}" class="btn btn-primary btn-sm">
                    <i class="ni ni-ruler-pencil"></i>
                </a>
                <a href="{{ url('/flows/' . $item->id . '/export') }}" class="btn btn-info btn-sm" title="Export Flow Data">
                    <i class="ni ni-archive-2"></i>
                </a>
                <a href="{{ url('/flows/' . $item->id . '/import') }}" class="btn btn-warning btn-sm" title="Import Flow Data">
                    <i class="ni ni-cloud-upload-96"></i>
                </a>
                <a href="{{ route('flows.delete',['flow'=>$item->id]) }}" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this flow?')">
                    <i class="ni ni ni-fat-remove"></i>
                </a>
            </td>
        </tr>
    @endforeach
@endsection
@section('customfooter')
@if(!empty($hasAiFlowAssistant))
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="mb-1">{{ __('AI Flow Assistant') }}</h3>
                <p class="text-muted small mb-3">{{ __('Describe what you want in plain language — we draft a flow you can review and publish.') }}</p>

                @php
                    $generateCost = (int) ($aiStatus['generate_cost'] ?? 5);
                    $canGenerate = !empty($aiStatus['can_generate']);
                    $hasOwnKey = !empty($aiStatus['has_own_key']);
                @endphp

                @if(!empty($aiStatus['enabled']) && ($aiStatus['monthly_allowance'] ?? 0) > 0)
                    <p class="small text-muted mb-2">
                        {{ __('AI credits remaining this billing period: :count of :total', [
                            'count' => $aiStatus['remaining'] ?? 0,
                            'total' => $aiStatus['monthly_allowance'] ?? 0,
                        ]) }}
                    </p>
                @endif

                @if($hasOwnKey)
                    <p class="small text-muted mb-2">{{ __('Using your OpenRouter API key — drafts do not use AI credits.') }}</p>
                @elseif($generateCost > 0)
                    <p class="small text-muted mb-2">
                        {{ trans_choice(':count AI credit per draft|:count AI credits per draft', $generateCost, ['count' => $generateCost]) }}
                    </p>
                @endif

                <form id="ai-flow-form" class="form-inline flex-wrap">
                    @csrf
                    <input type="text" name="description" class="form-control flex-grow-1 mb-2 mr-2" style="min-width: 280px;" placeholder="{{ __('When someone says pay, send M-Pesa STK and confirm...') }}" required minlength="10" @if(!$canGenerate) disabled @endif>
                    <input type="text" name="name" class="form-control mb-2 mr-2" placeholder="{{ __('Flow name (optional)') }}" @if(!$canGenerate) disabled @endif>
                    <button type="submit" class="btn btn-primary mb-2" @if(!$canGenerate) disabled @endif>{{ __('Generate draft') }}</button>
                </form>

                @if(!$canGenerate)
                    <p class="small text-warning mb-0 mt-2">
                        @if(($aiStatus['monthly_allowance'] ?? 0) <= 0 && !$hasOwnKey)
                            {{ __('Managed AI is available on Pro and above, or add your OpenRouter key in workspace settings.') }}
                        @else
                            {{ __('Not enough AI credits for a draft this billing period.') }}
                            <a href="{{ route('plans.current') }}">{{ __('View billing') }}</a>
                        @endif
                    </p>
                @endif

                <div id="ai-flow-result" class="small text-muted mt-2"></div>
            </div>
        </div>
    </div>
</div>
@else
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm border-light">
            <div class="card-body">
                <h3 class="mb-1">{{ __('AI Flow Assistant') }}</h3>
                <p class="text-muted small mb-2">{{ __('Generate draft flows from plain-language descriptions.') }}</p>
                <p class="small mb-0">
                    {{ __('Included on Pro and Agency plans.') }}
                    <a href="{{ route('plans.current') }}">{{ __('Upgrade plan') }}</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row mt-4">
    <div class="col-12 mb-3">
        <h3 class="mb-0">{{ __('Flow templates') }}</h3>
        <p class="text-muted small mb-0">{{ __('Install a pre-built automation, then customize it in the flow editor.') }}</p>
    </div>
    @foreach($templates as $key => $template)
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="mb-2">{{ $template['name'] }}</h5>
                    <p class="text-muted small flex-grow-1">{{ $template['description'] }}</p>
                    @if(!empty($template['setup_hint']))
                        <p class="small text-info mb-2">{{ $template['setup_hint'] }}</p>
                    @endif
                    @if(!empty($template['post_install_checklist']))
                        <ul class="small text-muted mb-0 ps-3">
                            @foreach($template['post_install_checklist'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if(!empty($template['requires_setup_wizard']))
                        <button type="button" class="btn btn-sm btn-primary mt-2 js-shop-wizard" data-template-key="{{ $key }}">
                            {{ __('Set up shop flow') }}
                        </button>
                    @else
                        <a href="{{ route('flows.create-from-template', $key) }}" class="btn btn-sm btn-outline-primary mt-2">
                            {{ __('Use template') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="modal fade" id="shopWizardModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Set up WhatsApp shop') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted" id="shop-wizard-hint"></p>
                <div class="form-group">
                    <label>{{ __('Catalog') }}</label>
                    <select id="shop-catalog" class="form-control"></select>
                </div>
                <div class="form-group">
                    <label>{{ __('Payment provider') }}</label>
                    <select id="shop-provider" class="form-control"></select>
                </div>
                <div class="form-group">
                    <label>{{ __('Fulfillment group') }}</label>
                    <select id="shop-group" class="form-control"><option value="">{{ __('Optional') }}</option></select>
                </div>
                <div class="form-group">
                    <label>{{ __('Journey') }}</label>
                    <select id="shop-journey" class="form-control"><option value="">{{ __('Optional') }}</option></select>
                </div>
                <div class="form-group">
                    <label>{{ __('Stage') }}</label>
                    <select id="shop-stage" class="form-control"><option value="">{{ __('Optional') }}</option></select>
                </div>
                <div class="form-group">
                    <label>{{ __('Keywords (comma separated)') }}</label>
                    <input type="text" id="shop-keywords" class="form-control" value="shop, buy" />
                </div>
                <p class="small text-danger" id="shop-wizard-error" style="display:none;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="shop-wizard-install">{{ __('Install & open editor') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
(function () {
    let currentTemplateKey = null;
    let journeyData = [];

    document.querySelectorAll('.js-shop-wizard').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentTemplateKey = btn.getAttribute('data-template-key');
            document.getElementById('shop-wizard-error').style.display = 'none';
            fetch('/flows/templates/' + currentTemplateKey + '/setup', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success) {
                        alert(data.message || 'Setup failed');
                        return;
                    }
                    document.getElementById('shop-wizard-hint').textContent = data.template.setup_hint || '';
                    const catalogSelect = document.getElementById('shop-catalog');
                    catalogSelect.innerHTML = '';
                    (data.catalogs || []).forEach(function (c) {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.name + (c.catalog_mode ? ' (' + c.catalog_mode + ')' : '');
                        catalogSelect.appendChild(opt);
                    });
                    const providerSelect = document.getElementById('shop-provider');
                    providerSelect.innerHTML = '';
                    (data.payment_providers || []).forEach(function (p) {
                        const opt = document.createElement('option');
                        opt.value = p.value;
                        opt.textContent = p.label;
                        providerSelect.appendChild(opt);
                    });
                    const groupSelect = document.getElementById('shop-group');
                    groupSelect.innerHTML = '<option value="">Optional</option>';
                    (data.groups || []).forEach(function (g) {
                        const opt = document.createElement('option');
                        opt.value = g.id;
                        opt.textContent = g.name;
                        groupSelect.appendChild(opt);
                    });
                    journeyData = data.journeys || [];
                    const journeySelect = document.getElementById('shop-journey');
                    journeySelect.innerHTML = '<option value="">Optional</option>';
                    journeyData.forEach(function (j) {
                        const opt = document.createElement('option');
                        opt.value = j.id;
                        opt.textContent = j.name;
                        journeySelect.appendChild(opt);
                    });
                    document.getElementById('shop-stage').innerHTML = '<option value="">Optional</option>';
                    $('#shopWizardModal').modal('show');
                });
        });
    });

    document.getElementById('shop-journey')?.addEventListener('change', function () {
        const stageSelect = document.getElementById('shop-stage');
        stageSelect.innerHTML = '<option value="">Optional</option>';
        const journey = journeyData.find(function (j) { return String(j.id) === String(this.value); }.bind(this));
        (journey?.stages || []).forEach(function (s) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            stageSelect.appendChild(opt);
        });
    });

    document.getElementById('shop-wizard-install')?.addEventListener('click', function () {
        const err = document.getElementById('shop-wizard-error');
        err.style.display = 'none';
        const catalogId = document.getElementById('shop-catalog').value;
        if (!catalogId) {
            err.textContent = 'Please select a catalog.';
            err.style.display = 'block';
            return;
        }
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('[name=_token]')?.value;
        fetch('/flows/templates/' + currentTemplateKey + '/install', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token || '',
            },
            body: JSON.stringify({
                catalog_id: catalogId,
                payment_provider: document.getElementById('shop-provider').value,
                group_id: document.getElementById('shop-group').value || null,
                journey_id: document.getElementById('shop-journey').value || null,
                stage_id: document.getElementById('shop-stage').value || null,
                keywords: document.getElementById('shop-keywords').value,
            }),
        })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (result) {
            if (result.ok && result.data.success) {
                window.location.href = result.data.edit_url;
            } else {
                err.textContent = result.data.message || 'Install failed';
                err.style.display = 'block';
            }
        })
        .catch(function () {
            err.textContent = 'Install failed';
            err.style.display = 'block';
        });
    });
})();
</script>
@if(!empty($hasAiFlowAssistant))
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
    .then(r => r.json().then(data => ({ ok: r.ok, data })))
    .then(({ ok, data }) => {
        if (ok && data.success) {
            result.innerHTML = data.summary + ' <a href="' + data.edit_url + '">{{ __("Open editor") }}</a>';
        } else {
            result.textContent = data.message || '{{ __("Generation failed") }}';
        }
    })
    .catch(() => result.textContent = '{{ __("Generation failed") }}');
});
</script>
@endif
@endpush
