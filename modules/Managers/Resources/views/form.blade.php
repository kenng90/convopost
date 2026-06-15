@extends('layouts.app')

@section('content')
<div class="container-fluid mt--7">
    <div class="row">
        <div class="col">
            <div class="card shadow">
                <div class="card-header border-0">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h3 class="mb-0">{{ $setup['title'] }}</h3>
                        </div>
                        <div class="col-4 text-right">
                            <a href="{{ $setup['action_link'] }}" class="btn btn-sm btn-primary">{{ $setup['action_name'] }}</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ $setup['action'] }}">
                        @csrf
                        @if($setup['isupdate'])
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label class="form-control-label">{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" required
                                    value="{{ old('name', $membership?->user?->name) }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-control-label">{{ __('Email') }}</label>
                                <input type="email" name="email" class="form-control" required
                                    value="{{ old('email', $membership?->user?->email) }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-control-label">{{ __('Password') }}</label>
                                <input type="password" name="password" class="form-control" {{ $setup['isupdate'] ? '' : 'required' }}>
                            </div>
                        </div>

                        @if($templates->isNotEmpty())
                        <div class="form-group">
                            <label class="form-control-label">{{ __('Apply role template') }}</label>
                            <select name="template_id" class="form-control">
                                <option value="">{{ __('Custom permissions') }}</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}" @selected(old('template_id') == $template->id)>
                                        {{ $template->name }}@if($template->is_system) ({{ __('System') }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <h4 class="mt-4">{{ __('Module access') }}</h4>
                        <p class="text-muted text-sm">{{ __('Managers cannot access workspace, billing, or WhatsApp Cloud setup.') }}</p>

                        <div class="row">
                            @foreach($modules as $module)
                                @php
                                    $checked = in_array($module['alias'], old('modules', $selectedModules), true);
                                    $permission = old('permissions.'.$module['alias'], $selectedPermissions[$module['alias']] ?? 'manage');
                                @endphp
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="module_{{ $module['alias'] }}"
                                            name="modules[]" value="{{ $module['alias'] }}" @checked($checked)>
                                        <label class="custom-control-label" for="module_{{ $module['alias'] }}">
                                            {{ $module['label'] }}
                                        </label>
                                    </div>
                                    <select name="permissions[{{ $module['alias'] }}]" class="form-control form-control-sm mt-1">
                                        <option value="manage" @selected($permission === 'manage')>{{ __('Full access') }}</option>
                                        <option value="view" @selected($permission === 'view')>{{ __('View only') }}</option>
                                    </select>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
