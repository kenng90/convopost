@extends('general.index', $setup)

@section('cardbody')
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ $setup['action'] }}" enctype="multipart/form-data">
        @csrf
        
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="form-control-label" for="flow_file">{{ __('Flow Data File') }}</label>
                    <input type="file" name="flow_file" id="flow_file" class="form-control" accept=".json" required>
                    <small class="form-text text-muted">{{ __('Upload a JSON file containing flow data. Maximum file size: 2MB') }}</small>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-warning">
                    <strong>{{ __('Warning:') }}</strong> {{ __('Importing flow data will completely replace the existing flow configuration for') }} <strong>{{ $flow->name }}</strong>. {{ __('This action cannot be undone.') }}
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">{{ __('Import Flow Data') }}</button>
            </div>
        </div>
    </form>
@endsection