@extends('layouts.app', ['title' => __('Campaign segments')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <h1 class="mb-0">🎯 {{ __('Audience segments') }}</h1>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
  <div class="row">
    <div class="col-lg-4">
      <div class="card shadow">
        <div class="card-header"><h3 class="mb-0">{{ __('New segment') }}</h3></div>
        <div class="card-body">
          <form method="POST" action="{{ route('campaigns.segments.store') }}">
            @csrf
            <div class="form-group">
              <label>{{ __('Name') }}</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group">
              <label>{{ __('Subscribed contacts only') }}</label>
              <input type="hidden" name="filters[0][field]" value="subscribed">
              <input type="hidden" name="filters[0][operator]" value="equals">
              <select name="filters[0][value]" class="form-control">
                <option value="1">{{ __('Yes') }}</option>
                <option value="0">{{ __('No') }}</option>
              </select>
            </div>
            <button class="btn btn-primary" type="submit">{{ __('Create segment') }}</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card shadow">
        <div class="card-header"><h3 class="mb-0">{{ __('Saved segments') }}</h3></div>
        <div class="card-body">
          <table class="table">
            <thead>
              <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Subscribed contacts') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @forelse ($segments as $segment)
                <tr>
                  <td>{{ $segment['model']->name }}</td>
                  <td>{{ $segment['subscribed_count'] }}</td>
                  <td>
                    <form method="POST" action="{{ route('campaigns.segments.destroy', $segment['model']) }}" onsubmit="return confirm('{{ __('Delete this segment?') }}')">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm btn-danger" type="submit">{{ __('Delete') }}</button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-muted">{{ __('No segments yet.') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
