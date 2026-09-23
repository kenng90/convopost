@extends('layouts.app', ['title' => __('Posting queue')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">{{ __('Posting queue') }}</h1>
                    <p class="mb-0 text-white opacity-8">{{ __('Define weekly time slots. “Add to queue” in the composer fills the next free slot.') }}</p>
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

        @if ($nextAt)
            <div class="col-12 mb-3">
                <div class="alert alert-info mb-0">
                    {{ __('Next free queue slot:') }}
                    <strong>{{ $nextAt->timezone(config('app.timezone'))->format('D, M j Y H:i') }}</strong>
                    <span class="text-muted">({{ config('app.timezone') }})</span>
                </div>
            </div>
        @endif

        <div class="col-12">
            <div class="card shadow">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">{{ __('Add slot') }}</h3>
                    <form method="POST" action="{{ route('social.queue.seed') }}" class="mb-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Use recommended times') }}</button>
                    </form>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('social.queue.store') }}" class="form-row align-items-end">
                        @csrf
                        <div class="form-group col-md-4">
                            <label class="form-control-label" for="queue-weekday">{{ __('Weekday') }}</label>
                            <select id="queue-weekday" name="weekday" class="form-control @error('weekday') is-invalid @enderror" required>
                                @foreach ($weekdays as $value => $label)
                                    <option value="{{ $value }}" @selected((string) old('weekday', '1') === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('weekday') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group col-md-3">
                            <label class="form-control-label" for="queue-time">{{ __('Time') }}</label>
                            <input id="queue-time" type="time" name="time" value="{{ old('time', '09:00') }}" class="form-control @error('time') is-invalid @enderror" required>
                            @error('time') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group col-md-3">
                            <label class="form-control-label" for="queue-timezone">{{ __('Timezone') }}</label>
                            <input id="queue-timezone" type="text" name="timezone" value="{{ old('timezone', config('app.timezone')) }}" class="form-control @error('timezone') is-invalid @enderror" placeholder="Africa/Nairobi">
                            @error('timezone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
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
                    <h3 class="mb-0">{{ __('Your slots') }}</h3>
                </div>
                @if ($slots->count())
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('Weekday') }}</th>
                                    <th>{{ __('Time') }}</th>
                                    <th>{{ __('Timezone') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($slots as $slot)
                                    <tr>
                                        <td>{{ $slot->weekdayLabel() }}</td>
                                        <td>{{ $slot->time }}</td>
                                        <td>{{ $slot->timezone }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('social.queue.destroy', $slot) }}" onsubmit="return confirm(@json(__('Delete this queue slot?')));">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="card-body">
                        <p class="mb-0 text-muted">{{ __('No queue slots yet. Add times above or use recommended times.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
