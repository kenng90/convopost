@extends('layouts.app', ['title' => __('Campaign integrations')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <h1 class="mb-3 mt--3">🔌 {{ __('Campaign integrations hub') }}</h1>
            <p class="text-muted">{{ __('Connect store events and outbound webhooks to API campaigns.') }}</p>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
  <div class="row">
    <div class="col-lg-6 mb-4">
      <div class="card shadow">
        <div class="card-header"><h3 class="mb-0">{{ __('E-commerce triggers') }}</h3></div>
        <div class="card-body">
          <form method="POST" action="{{ route('campaigns.integrations.triggers.store') }}">
            @csrf
            <div class="form-group">
              <label>{{ __('Event') }}</label>
              <select name="event_type" class="form-control" required>
                @foreach ($events as $key => $label)
                  <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <label>{{ __('API campaign') }}</label>
              <select name="campaign_id" class="form-control" required>
                @forelse ($apiCampaigns as $id => $name)
                  <option value="{{ $id }}">{{ $name }} (ID: {{ $id }})</option>
                @empty
                  <option value="">{{ __('Create an API campaign first') }}</option>
                @endforelse
              </select>
            </div>
            <div class="form-group">
              <label>{{ __('Outbound webhook URL') }}</label>
              <input type="url" name="campaign_webhook_url" class="form-control" value="{{ $webhookUrl }}" placeholder="https://...">
              <small class="text-muted">{{ __('Receives campaign.completed and message.failed events.') }}</small>
            </div>
            <button class="btn btn-primary" type="submit">{{ __('Save trigger') }}</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-6 mb-4">
      <div class="card shadow">
        <div class="card-header"><h3 class="mb-0">{{ __('Active triggers') }}</h3></div>
        <div class="card-body">
          @forelse ($triggers as $trigger)
            <div class="d-flex justify-content-between border-bottom py-2">
              <div>
                <strong>{{ $events[$trigger->event_type] ?? $trigger->event_type }}</strong><br>
                <small>{{ $trigger->campaign?->name }}</small>
              </div>
              <span class="badge badge-{{ $trigger->is_active ? 'success' : 'secondary' }}">
                {{ $trigger->is_active ? __('Active') : __('Inactive') }}
              </span>
            </div>
          @empty
            <p class="text-muted mb-0">{{ __('No triggers configured yet.') }}</p>
          @endforelse
        </div>
      </div>

      <div class="card shadow mt-4">
        <div class="card-header"><h3 class="mb-0">{{ __('REST API') }}</h3></div>
        <div class="card-body">
          <p class="text-muted">{{ __('Use API campaigns programmatically or connect Zapier/Make.') }}</p>
          <a href="{{ $apiDocs }}" target="_blank" class="btn btn-outline-primary">{{ __('API documentation') }}</a>
          <a href="{{ route('wpbox.api.index', ['type' => 'api']) }}" class="btn btn-outline-secondary ml-2">{{ __('Manage API campaigns') }}</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
