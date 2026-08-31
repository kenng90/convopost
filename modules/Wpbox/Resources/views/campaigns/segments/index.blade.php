@extends('layouts.app', ['title' => __('Campaign segments')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <h1 class="mb-0">🎯 {{ __('Audience segments') }}</h1>
            <p class="text-white mb-0">{{ __('Use channel filters so WhatsApp template campaigns never pull Instagram or Messenger-only contacts.') }}</p>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
  <div class="row">
    <div class="col-lg-5">
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
              <label>{{ __('Subscribed contacts') }}</label>
              <input type="hidden" name="filters[0][field]" value="subscribed">
              <input type="hidden" name="filters[0][operator]" value="equals">
              <select name="filters[0][value]" class="form-control">
                <option value="1">{{ __('Yes') }}</option>
                <option value="0">{{ __('No') }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>{{ __('Has messaging channel') }}</label>
              <input type="hidden" name="filters[1][field]" value="has_channel">
              <input type="hidden" name="filters[1][operator]" value="equals">
              <select name="filters[1][value]" class="form-control">
                <option value="">{{ __('Any') }}</option>
                <option value="whatsapp">{{ __('WhatsApp (or phone)') }}</option>
                <option value="instagram">{{ __('Instagram') }}</option>
                <option value="messenger">{{ __('Messenger') }}</option>
                <option value="tiktok">{{ __('TikTok') }}</option>
              </select>
              <small class="text-muted">{{ __('For WhatsApp template campaigns, choose WhatsApp.') }}</small>
            </div>
            <div class="form-group">
              <label>{{ __('Has phone number') }}</label>
              <input type="hidden" name="filters[2][field]" value="phone_present">
              <input type="hidden" name="filters[2][operator]" value="equals">
              <select name="filters[2][value]" class="form-control">
                <option value="">{{ __('Any') }}</option>
                <option value="1">{{ __('Yes') }}</option>
                <option value="0">{{ __('No (social-only)') }}</option>
              </select>
            </div>
            <button class="btn btn-primary" type="submit">{{ __('Create segment') }}</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="card shadow">
        <div class="card-header"><h3 class="mb-0">{{ __('Saved segments') }}</h3></div>
        <div class="card-body">
          <table class="table">
            <thead>
              <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Filters') }}</th>
                <th>{{ __('Subscribed contacts') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @forelse ($segments as $segment)
                <tr>
                  <td>{{ $segment['model']->name }}</td>
                  <td>
                    @foreach (($segment['model']->filters ?? []) as $filter)
                      @if(!empty($filter['value']))
                        <span class="badge badge-light">{{ $filter['field'] }}={{ $filter['value'] }}</span>
                      @endif
                    @endforeach
                  </td>
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
                <tr><td colspan="4" class="text-muted">{{ __('No segments yet.') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
