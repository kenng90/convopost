@extends('layouts.app', ['title' => __('ConvoConnect — :name', ['name' => $company->name])])
@section('content')
<div class="header pb-6 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="mb-0">📲 {{ $company->name }}</h1>
                    <p class="text-muted mb-0">{{ __('ConvoConnect SMS details') }}</p>
                </div>
                <div class="col-auto">
                    <a href="{{ route('admin.convoconnect.index') }}" class="btn btn-sm btn-secondary">{{ __('Back to list') }}</a>
                    <a href="{{ route('admin.companies.edit', $company) }}" class="btn btn-sm btn-info">{{ __('Edit organization') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt--6">
    @include('partials.flash')

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="mb-0">{{ __('Status') }}</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('ConvoConnect integration') }}</dt>
                        <dd class="col-sm-8">{{ $detail['platform_enabled'] ? __('Enabled') : __('Disabled') }}</dd>

                        <dt class="col-sm-4">{{ __('Platform credentials') }}</dt>
                        <dd class="col-sm-8">{{ $detail['reseller_configured'] ? __('Yes') : __('No') }}</dd>

                        <dt class="col-sm-4">{{ __('Provisioned') }}</dt>
                        <dd class="col-sm-8">{{ $detail['provisioned'] ? __('Yes') : __('No') }}</dd>

                        <dt class="col-sm-4">{{ __('Sender ID') }}</dt>
                        <dd class="col-sm-8">
                            <code>{{ $detail['sender_id'] ?: '—' }}</code>
                            @if($detail['sender_status'])
                                <span class="badge badge-{{ $presenter->senderStatusBadgeClass($detail['sender_status']) }} ml-2">
                                    {{ str_replace('_', ' ', $detail['sender_status']) }}
                                </span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">{{ __('SMS ready') }}</dt>
                        <dd class="col-sm-8">
                            @if($detail['sms_ready'])
                                <span class="badge badge-success">{{ __('Yes') }}</span>
                            @else
                                <span class="badge badge-warning">{{ __('No') }}</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">{{ __('SMS channel') }}</dt>
                        <dd class="col-sm-8"><code>{{ $detail['resolved_provider'] }}</code></dd>

                        <dt class="col-sm-4">{{ __('Tenant message') }}</dt>
                        <dd class="col-sm-8">{{ $detail['status_message'] }}</dd>

                        @if($detail['live_account'])
                            <dt class="col-sm-4">{{ __('Live SMS balance') }}</dt>
                            <dd class="col-sm-8"><strong>{{ data_get($detail['live_account'], 'smsBalance', '—') }}</strong></dd>
                        @endif

                        @if($detail['live_error'])
                            <dt class="col-sm-4">{{ __('Live API error') }}</dt>
                            <dd class="col-sm-8 text-danger">{{ $detail['live_error'] }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">{{ __('Credentials (platform only)') }}</h3>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="toggle-secrets">{{ __('Reveal secrets') }}</button>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('Sub-account login') }}</dt>
                        <dd class="col-sm-8"><code>{{ $detail['sub_login'] ?: '—' }}</code></dd>

                        <dt class="col-sm-4">{{ __('User ID') }}</dt>
                        <dd class="col-sm-8"><code>{{ $detail['user_id'] ?: '—' }}</code></dd>

                        <dt class="col-sm-4">{{ __('API key') }}</dt>
                        <dd class="col-sm-8">
                            <code class="secret-masked">{{ $detail['api_key_masked'] ?: '—' }}</code>
                            <code class="secret-plain d-none">{{ $detail['api_key'] ?: '—' }}</code>
                        </dd>

                        <dt class="col-sm-4">{{ __('Password') }}</dt>
                        <dd class="col-sm-8">
                            <code class="secret-masked">{{ $detail['password_masked'] ?: '—' }}</code>
                            <code class="secret-plain d-none">{{ $detail['password'] ?: '—' }}</code>
                        </dd>

                        <dt class="col-sm-4">{{ __('DLR webhook URL') }}</dt>
                        <dd class="col-sm-8"><code>{{ $detail['webhook_url'] }}</code></dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="mb-0">{{ __('Set credentials manually') }}</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted small">{{ __('Use this when a sub-account already exists on the gateway or auto-provisioning failed partway through.') }}</p>

                    <form method="POST" action="{{ route('admin.convoconnect.store-credentials', $company) }}" class="row g-3">
                        @csrf

                        <div class="col-md-6">
                            <label class="form-label" for="sub_login">{{ __('Sub-account login') }}</label>
                            <input type="text" name="sub_login" id="sub_login" minlength="5" maxlength="15" pattern="[A-Za-z0-9]+"
                                class="form-control @error('sub_login') is-invalid @enderror"
                                value="{{ old('sub_login', $detail['sub_login'] ?: $detail['expected_sub_login']) }}" required>
                            <small class="text-muted">{{ __('5-15 characters, letters and numbers only.') }}</small>
                            @error('sub_login')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="user_id">{{ __('User ID') }}</label>
                            <input type="text" name="user_id" id="user_id" maxlength="15" pattern="[A-Za-z0-9]+"
                                class="form-control @error('user_id') is-invalid @enderror"
                                value="{{ old('user_id', $detail['user_id']) }}"
                                placeholder="{{ __('Defaults to sub-account login') }}">
                            @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="api_key">{{ __('API key') }}</label>
                            <input type="text" name="api_key" id="api_key"
                                class="form-control @error('api_key') is-invalid @enderror"
                                value="{{ old('api_key') }}"
                                placeholder="{{ $detail['provisioned'] ? __('Leave blank to keep current key') : '' }}"
                                @required(! $detail['provisioned'])>
                            @if($detail['provisioned'])
                                <small class="text-muted">{{ __('Leave blank to keep the current API key.') }}</small>
                            @endif
                            @error('api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="password">{{ __('Password') }}</label>
                            <input type="password" name="password" id="password"
                                class="form-control @error('password') is-invalid @enderror"
                                autocomplete="new-password"
                                placeholder="{{ $detail['provisioned'] ? __('Leave blank to keep current password') : '' }}">
                            @if($detail['provisioned'])
                                <small class="text-muted">{{ __('Leave blank to keep the current password.') }}</small>
                            @endif
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="sender_id">{{ __('Sender ID') }}</label>
                            <input type="text" name="sender_id" id="sender_id" maxlength="11" pattern="[A-Za-z0-9]+"
                                class="form-control @error('sender_id') is-invalid @enderror"
                                value="{{ old('sender_id', $detail['sender_id']) }}" required>
                            @error('sender_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="sender_status">{{ __('Sender ID status') }}</label>
                            <select name="sender_status" id="sender_status" class="form-control @error('sender_status') is-invalid @enderror" required>
                                @php
                                    $selectedSenderStatus = old(
                                        'sender_status',
                                        in_array($detail['sender_status'], ['pending_approval', 'approved', 'request_failed'], true)
                                            ? $detail['sender_status']
                                            : 'pending_approval'
                                    );
                                @endphp
                                @foreach (['pending_approval', 'approved', 'request_failed'] as $status)
                                    <option value="{{ $status }}" @selected($selectedSenderStatus === $status)>
                                        {{ str_replace('_', ' ', $status) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('sender_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">{{ __('Save credentials') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            @if(!empty($detail['live_sender_ids']))
                <div class="card mb-4">
                    <div class="card-header"><h3 class="mb-0">{{ __('Live Sender IDs') }}</h3></div>
                    <div class="card-body">
                        <pre class="mb-0 small">{{ json_encode($detail['live_sender_ids'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h3 class="mb-0">{{ __('Actions') }}</h3></div>
                <div class="card-body d-flex flex-column gap-3">
                    <form method="POST" action="{{ route('admin.convoconnect.provision', $company) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-block" @disabled($detail['provisioned'])>
                            {{ __('Provision sub-account') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.convoconnect.resume-provision', $company) }}">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-block" @disabled($detail['provisioned'])>
                            {{ __('Resume provisioning') }}
                        </button>
                        <p class="text-muted small mb-0 mt-2">
                            {{ __('Use when a gateway user already exists (for example :login) but credentials are missing here.', ['login' => $detail['expected_sub_login']]) }}
                        </p>
                    </form>

                    <form method="POST" action="{{ route('admin.convoconnect.approve-sender', $company) }}">
                        @csrf
                        <button type="submit" class="btn btn-success btn-block" @disabled(! $detail['provisioned'] || $detail['sender_status'] === 'approved')>
                            {{ __('Mark Sender ID approved') }}
                        </button>
                    </form>

                    <a href="{{ route('admin.convoconnect.show', ['company' => $company, 'live' => 1]) }}" class="btn btn-info btn-block">
                        {{ __('Refresh live balance') }}
                    </a>

                    <form method="POST" action="{{ route('admin.convoconnect.sync-credits', $company) }}" class="mt-2">
                        @csrf
                        <label class="form-label">{{ __('Add ConvoConnect SMS credits') }}</label>
                        <div class="input-group">
                            <input type="number" name="credits" min="1" step="1" class="form-control" placeholder="100" required>
                            <button type="submit" class="btn btn-outline-primary">{{ __('Add') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="mb-0">{{ __('Owner') }}</h3></div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $company->user?->name }}</strong></p>
                    <p class="mb-0 text-muted">{{ $company->user?->email }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('toggle-secrets')?.addEventListener('click', function () {
    const show = document.querySelectorAll('.secret-plain').length && document.querySelector('.secret-plain').classList.contains('d-none');
    document.querySelectorAll('.secret-masked').forEach(el => el.classList.toggle('d-none', show));
    document.querySelectorAll('.secret-plain').forEach(el => el.classList.toggle('d-none', !show));
    this.textContent = show ? '{{ __('Hide secrets') }}' : '{{ __('Reveal secrets') }}';
});
</script>
@endsection
