@extends('layouts.app', ['title' => __('Social labels')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Labels') }}</h1>
                    <p class="mb-0 text-white opacity-8">{{ __('Organize posts with colored labels.') }}</p>
                </div>
                <div class="col-auto">
                    <a href="{{ route('social.posts.index') }}" class="btn btn-sm btn-primary">{{ __('Posts') }}</a>
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
                    <h3 class="mb-0">{{ __('New label') }}</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('social.labels.store') }}" class="form-row align-items-end">
                        @csrf
                        <div class="form-group col-md-5">
                            <label class="form-control-label" for="label-name">{{ __('Name') }}</label>
                            <input id="label-name" type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                            @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group col-md-3">
                            <label class="form-control-label" for="label-color">{{ __('Color') }}</label>
                            <input id="label-color" type="color" name="color" value="{{ old('color', '#0E8A7A') }}" class="form-control @error('color') is-invalid @enderror" style="height: 42px;">
                            @error('color') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group col-md-2">
                            <button type="submit" class="btn btn-primary btn-block">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card shadow">
                <div class="card-header border-0">
                    <h3 class="mb-0">{{ __('Your labels') }}</h3>
                </div>
                @if ($labels->count())
                    <ul class="list-group list-group-flush">
                        @foreach ($labels as $label)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <span class="badge mr-2" style="background-color: {{ $label->color }};">&nbsp;</span>
                                    {{ $label->name }}
                                </span>
                                <form method="POST" action="{{ route('social.labels.destroy', $label) }}" onsubmit="return confirm(@json(__('Delete this label?')));">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                    <div class="card-footer py-4">
                        {{ $labels->links() }}
                    </div>
                @else
                    <div class="card-body">
                        <p class="mb-0 text-muted">{{ __('No labels yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
