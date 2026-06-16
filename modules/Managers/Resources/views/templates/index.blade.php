@extends('layouts.app')

@section('content')
<div class="container-fluid mt--7">
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">{{ __('Role templates') }}</h3>
                    <a href="{{ $backLink }}" class="btn btn-sm btn-outline-primary">{{ __('Back to managers') }}</a>
                </div>
                <div class="card-body">
                    @forelse($templates as $template)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>{{ $template->name }}</strong>
                                    @if($template->is_system)
                                        <span class="badge badge-info ml-1">{{ __('System') }}</span>
                                    @endif
                                    @if($template->description)
                                        <p class="text-muted text-sm mb-1 mt-1">{{ $template->description }}</p>
                                    @endif
                                    <p class="text-sm mb-0">
                                        {{ $template->modules->map(fn ($m) => $m->module_alias.' ('.$m->permission.')')->join(', ') }}
                                    </p>
                                </div>
                                @if(!$template->is_system && $template->company_id)
                                    <a href="{{ route('orgmanager.templates.delete', $template) }}" class="btn btn-danger btn-sm">{{ __('Delete') }}</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted">{{ __('No templates yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Create template') }}</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('orgmanager.templates.store') }}">
                        @csrf
                        <div class="form-group">
                            <label>{{ __('Name') }}</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>{{ __('Description') }}</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <h5 class="mt-3">{{ __('Module access') }}</h5>
                        <div class="row">
                            @foreach($modules as $module)
                                <div class="col-md-6 mb-3">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="tpl_{{ $module['alias'] }}"
                                            name="modules[]" value="{{ $module['alias'] }}">
                                        <label class="custom-control-label" for="tpl_{{ $module['alias'] }}">{{ $module['label'] }}</label>
                                    </div>
                                    <select name="permissions[{{ $module['alias'] }}]" class="form-control form-control-sm mt-1">
                                        <option value="manage">{{ __('Full access') }}</option>
                                        <option value="view">{{ __('View only') }}</option>
                                    </select>
                                </div>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">{{ __('Create template') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
