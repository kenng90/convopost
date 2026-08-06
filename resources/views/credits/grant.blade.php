@extends('layouts.app', ['title' => __('Grant credits')])

@section('content')
    <div class="header pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center">
                    <div class="col">
                        <h1 class="mb-3 mt--3">{{ __('Grant credits') }}</h1>
                        <p class="text-muted mb-0">
                            {{ __('Manually add messaging or AI credits for an organization owner. Messaging top-ups expire on the date you set. AI top-ups apply to the current billing period.') }}
                        </p>
                    </div>
                    <div class="col-auto">
                        <a href="{{ route('credits.index') }}" class="btn btn-sm btn-outline-primary">
                            {{ __('Credit costs') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col-lg-8">
                @include('partials.flash')

                <div class="card shadow">
                    <div class="card-header border-0">
                        <h3 class="mb-0">{{ __('Add credits') }}</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('credits.create') }}" class="mb-4">
                            <label class="form-control-label" for="company_id_lookup">{{ __('Organization') }}</label>
                            <div class="input-group">
                                <select name="company_id" id="company_id_lookup" class="form-control" onchange="this.form.submit()">
                                    <option value="">{{ __('Select organization…') }}</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}" @selected($selectedCompanyId === (int) $company->id)>
                                            {{ $company->name }}
                                            @if ($company->user)
                                                — {{ $company->user->email }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </form>

                        @if ($selectedCompanyId && $walletSummary)
                            <div class="alert alert-info">
                                <div class="mb-1">
                                    <strong>{{ __('Messaging available') }}:</strong>
                                    {{ number_format($walletSummary['messaging_available']) }}
                                </div>
                                <div class="mb-0">
                                    <strong>{{ __('AI remaining') }}:</strong>
                                    {{ number_format($walletSummary['ai_remaining']) }}
                                    / {{ number_format($walletSummary['ai_allowance']) }}
                                    @if (($walletSummary['ai_bonus'] ?? 0) > 0)
                                        <span class="text-muted">({{ __('includes :bonus bonus', ['bonus' => number_format($walletSummary['ai_bonus'])]) }})</span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('credits.store') }}">
                            @csrf
                            <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">

                            <div class="form-group">
                                <label class="form-control-label" for="messaging_credits">{{ __('Messaging credits') }}</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    name="messaging_credits"
                                    id="messaging_credits"
                                    class="form-control @error('messaging_credits') is-invalid @enderror"
                                    value="{{ old('messaging_credits', 0) }}"
                                    @disabled(! $selectedCompanyId)
                                >
                                @error('messaging_credits')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    {{ __('Used for campaigns, bot replies, M-Pesa STK, and other messaging actions. Survives plan changes.') }}
                                </small>
                            </div>

                            <div class="form-group">
                                <label class="form-control-label" for="messaging_expires_at">{{ __('Messaging expiry') }}</label>
                                <input
                                    type="date"
                                    name="messaging_expires_at"
                                    id="messaging_expires_at"
                                    class="form-control @error('messaging_expires_at') is-invalid @enderror"
                                    value="{{ old('messaging_expires_at', $defaultExpiry) }}"
                                    @disabled(! $selectedCompanyId)
                                >
                                @error('messaging_expires_at')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-control-label" for="ai_credits">{{ __('AI credits') }}</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    name="ai_credits"
                                    id="ai_credits"
                                    class="form-control @error('ai_credits') is-invalid @enderror"
                                    value="{{ old('ai_credits', 0) }}"
                                    @disabled(! $selectedCompanyId)
                                >
                                @error('ai_credits')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    {{ __('Adds to the owner’s managed AI allowance for the current billing period. Resets when the period rolls over.') }}
                                </small>
                            </div>

                            <div class="form-group">
                                <label class="form-control-label" for="note">{{ __('Note') }} ({{ __('optional') }})</label>
                                <input
                                    type="text"
                                    name="note"
                                    id="note"
                                    maxlength="255"
                                    class="form-control @error('note') is-invalid @enderror"
                                    value="{{ old('note') }}"
                                    placeholder="{{ __('e.g. Mid-month top-up for spa bot') }}"
                                    @disabled(! $selectedCompanyId)
                                >
                                @error('note')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-success" @disabled(! $selectedCompanyId)>
                                {{ __('Grant credits') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
