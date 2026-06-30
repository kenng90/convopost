@extends('general.index', $setup)
@section('cardbody')
<form action="{{ $setup['action'] }}" method="POST" enctype="multipart/form-data">
    @csrf
    @isset($setup['inrow'])
        <div class="row">
    @endisset
        @include('partials.fields',['fiedls'=>$fields])
    @isset($setup['inrow'])
        </div>
    @endisset

    <div class="card mt-4 border-primary">
        <div class="card-header">
            <h3 class="mb-0">{{ __('First session date & time') }}</h3>
            <p class="text-sm text-muted mb-0 mt-1">
                {{ __('Add when this event takes place. Required for flows and public booking. Times use the event timezone above.') }}
            </p>
        </div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-control-label">{{ __('Starts at') }}</label>
                <input type="datetime-local" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at') }}" required>
                @error('starts_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-control-label">{{ __('Ends at') }}</label>
                <input type="datetime-local" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at') }}" required>
                @error('ends_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-control-label">{{ __('Capacity') }}</label>
                <input type="number" name="capacity" class="form-control" value="{{ old('capacity', 50) }}" min="1" required>
            </div>
            <div class="col-md-2">
                <label class="form-control-label">{{ __('Session status') }}</label>
                <select name="status" class="form-control">
                    <option value="published" @selected(old('status', 'published') === 'published')>{{ __('Published') }}</option>
                    <option value="draft" @selected(old('status') === 'draft')>{{ __('Draft') }}</option>
                </select>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary mt-3">{{ __('Create event') }}</button>
</form>
@endsection
