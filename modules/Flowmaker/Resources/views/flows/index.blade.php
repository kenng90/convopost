@extends('general.index', $setup)
@section('thead')
    <th>{{ __('Name') }}</th>
    <th>{{ __('crud.actions') }}</th>
@endsection
@section('tbody')
    @foreach ($setup['items'] as $item)
        <tr>
            <td>{{ $item->name }}</td>
           
           
            
            <td>
                <!-- FLOW MAKER -->
                <a href="{{ route('flowmaker.edit',['flow'=>$item->id]) }}" class="btn btn-success btn-sm">
                    <i class="ni ni-ruler-pencil"></i> {{ __('Flow maker')}}
                </a>

                <!-- EDIT -->
                <a href="{{ route('flows.edit',['flow'=>$item->id]) }}" class="btn btn-primary btn-sm">
                    <i class="ni ni-ruler-pencil"></i>
                </a>

                <!-- EXPORT -->
                <a href="{{ url('/flows/' . $item->id . '/export') }}" class="btn btn-info btn-sm" title="Export Flow Data">
                    <i class="ni ni-archive-2"></i>
                </a>

                <!-- IMPORT -->
                <a href="{{ url('/flows/' . $item->id . '/import') }}" class="btn btn-warning btn-sm" title="Import Flow Data">
                    <i class="ni ni-cloud-upload-96"></i>
                </a>

                <!-- DELETE -->
                <a href="{{ route('flows.delete',['flow'=>$item->id]) }}" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this flow?')">
                    <i class="ni ni ni-fat-remove"></i>
                </a>
            </td>
          
        </tr> 
    @endforeach
@endsection
@section('customfooter')
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="mb-1">{{ __('AI Flow Assistant') }}</h3>
                <p class="text-muted small mb-3">{{ __('Describe what you want in plain language — we draft a flow you can review and publish.') }}</p>
                @if(!empty($aiStatus['enabled']) && ($aiStatus['monthly_allowance'] ?? 0) > 0)
                    <p class="small text-muted mb-3">
                        {{ __('AI credits remaining this month: :count', ['count' => $aiStatus['remaining'] ?? 0]) }}
                    </p>
                @endif
                <form id="ai-flow-form" class="form-inline flex-wrap">
                    @csrf
                    <input type="text" name="description" class="form-control flex-grow-1 mb-2 mr-2" style="min-width: 280px;" placeholder="{{ __('When someone says pay, send M-Pesa STK and confirm...') }}" required minlength="10">
                    <input type="text" name="name" class="form-control mb-2 mr-2" placeholder="{{ __('Flow name (optional)') }}">
                    <button type="submit" class="btn btn-primary mb-2">{{ __('Generate draft') }}</button>
                </form>
                <div id="ai-flow-result" class="small text-muted mt-2"></div>
            </div>
        </div>
    </div>
</div>

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
                        <p class="small text-info mb-0">{{ $template['setup_hint'] }}</p>
                    @endif
                    <a href="{{ route('flows.create-from-template', $key) }}" class="btn btn-sm btn-outline-primary mt-2">
                        {{ __('Use template') }}
                    </a>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection

@push('js')
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
@endpush
