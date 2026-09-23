@extends('layouts.app', ['title' => __('Social templates')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Templates') }}</h1>
                    <p class="mb-0 text-white opacity-8">{{ __('Save reusable captions for faster composing.') }}</p>
                </div>
                <div class="col-auto">
                    <a href="{{ route('social.posts.create') }}" class="btn btn-sm btn-primary">{{ __('Compose') }}</a>
                    <a href="{{ route('social.home') }}" class="btn btn-sm btn-neutral">{{ __('Back to Social') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
    <div class="row mb-4">
        <div class="col-12">
            @include('partials.flash')
        </div>
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('New template') }}</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('social.templates.store') }}">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label class="form-control-label" for="template-name">{{ __('Name') }}</label>
                                <input id="template-name" type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                                @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label class="form-control-label" for="template-category">{{ __('Category') }}</label>
                                <input id="template-category" type="text" name="category" value="{{ old('category') }}" class="form-control @error('category') is-invalid @enderror" placeholder="{{ __('promo, launch…') }}">
                                @error('category') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-group col-md-2 d-flex align-items-end">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" class="custom-control-input" id="template-active" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="template-active">{{ __('Active') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-control-label" for="template-content">{{ __('Content') }}</label>
                            <textarea id="template-content" name="content" rows="4" class="form-control @error('content') is-invalid @enderror" required>{{ old('content') }}</textarea>
                            @error('content') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('Save template') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Your templates') }}</h3>
                </div>
                @if ($templates->count())
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Content') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($templates as $template)
                                    <tr wire:key="template-{{ $template->id }}">
                                        <td>{{ $template->name }}</td>
                                        <td>{{ $template->category ?: '—' }}</td>
                                        <td style="max-width: 320px;">
                                            <div class="text-truncate">{{ \Illuminate\Support\Str::limit($template->content, 100) }}</div>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $template->is_active ? 'success' : 'secondary' }}">
                                                {{ $template->is_active ? __('Active') : __('Inactive') }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-toggle="collapse"
                                                data-target="#edit-template-{{ $template->id }}"
                                            >
                                                {{ __('Edit') }}
                                            </button>
                                            <form method="POST" action="{{ route('social.templates.destroy', $template) }}" class="d-inline" onsubmit="return confirm(@json(__('Delete this template?')));">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr class="collapse" id="edit-template-{{ $template->id }}">
                                        <td colspan="5">
                                            <form method="POST" action="{{ route('social.templates.update', $template) }}" class="p-3 bg-light">
                                                @csrf
                                                @method('PUT')
                                                <div class="form-row">
                                                    <div class="form-group col-md-4">
                                                        <label class="form-control-label">{{ __('Name') }}</label>
                                                        <input type="text" name="name" value="{{ old('name', $template->name) }}" class="form-control" required>
                                                    </div>
                                                    <div class="form-group col-md-3">
                                                        <label class="form-control-label">{{ __('Category') }}</label>
                                                        <input type="text" name="category" value="{{ old('category', $template->category) }}" class="form-control">
                                                    </div>
                                                    <div class="form-group col-md-2 d-flex align-items-end">
                                                        <div class="custom-control custom-checkbox mb-2">
                                                            <input type="hidden" name="is_active" value="0">
                                                            <input type="checkbox" class="custom-control-input" id="edit-active-{{ $template->id }}" name="is_active" value="1" {{ $template->is_active ? 'checked' : '' }}>
                                                            <label class="custom-control-label" for="edit-active-{{ $template->id }}">{{ __('Active') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-control-label">{{ __('Content') }}</label>
                                                    <textarea name="content" rows="3" class="form-control" required>{{ old('content', $template->content) }}</textarea>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm">{{ __('Update') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer py-4">
                        {{ $templates->links() }}
                    </div>
                @else
                    <div class="card-body">
                        <p class="mb-0 text-muted">{{ __('No templates yet. Create one above.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
