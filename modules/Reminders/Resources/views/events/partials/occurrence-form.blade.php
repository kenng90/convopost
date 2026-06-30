<div class="card {{ $cardClass ?? 'mt-4' }}">
    <div class="card-header">
        <h3 class="mb-0">{{ $title ?? __('Event date & time') }}</h3>
        @isset($subtitle)
            <p class="text-sm text-muted mb-0 mt-1">{{ $subtitle }}</p>
        @endisset
    </div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $action }}" class="row g-3">
            @csrf
            <div class="col-md-3">
                <label class="form-control-label">{{ __('Starts at') }}</label>
                <input
                    type="datetime-local"
                    name="starts_at"
                    class="form-control @error('starts_at') is-invalid @enderror"
                    value="{{ old('starts_at') }}"
                    required
                >
                @error('starts_at')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-3">
                <label class="form-control-label">{{ __('Ends at') }}</label>
                <input
                    type="datetime-local"
                    name="ends_at"
                    class="form-control @error('ends_at') is-invalid @enderror"
                    value="{{ old('ends_at') }}"
                    required
                >
                @error('ends_at')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-2">
                <label class="form-control-label">{{ __('Capacity') }}</label>
                <input
                    type="number"
                    name="capacity"
                    class="form-control @error('capacity') is-invalid @enderror"
                    value="{{ old('capacity', 50) }}"
                    min="1"
                    required
                >
            </div>
            <div class="col-md-2">
                <label class="form-control-label">{{ __('Status') }}</label>
                <select name="status" class="form-control">
                    <option value="published" @selected(old('status', 'published') === 'published')>{{ __('Published') }}</option>
                    <option value="draft" @selected(old('status') === 'draft')>{{ __('Draft') }}</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">{{ $submitLabel ?? __('Add session') }}</button>
            </div>
        </form>

        @isset($tableOccurrences)
            <div class="table-responsive mt-4">
                <table class="table align-items-center">
                    <thead>
                        <tr>
                            <th>{{ __('Starts') }}</th>
                            <th>{{ __('Ends') }}</th>
                            <th>{{ __('Capacity') }}</th>
                            <th>{{ __('Seats left') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tableOccurrences as $occurrence)
                            <tr>
                                <td>{{ $occurrence->starts_at?->timezone($eventTimezone ?? 'UTC')->format('Y-m-d g:i A') }}</td>
                                <td>{{ $occurrence->ends_at?->timezone($eventTimezone ?? 'UTC')->format('Y-m-d g:i A') }}</td>
                                <td>{{ $occurrence->capacity }}</td>
                                <td>{{ $occurrence->seatsRemaining() }}</td>
                                <td><span class="badge badge-default">{{ ucfirst($occurrence->status) }}</span></td>
                                <td>
                                    <a href="{{ route('reminders.events.occurrences.delete', ['event' => $eventId, 'occurrence' => $occurrence->id]) }}" class="btn btn-danger btn-sm">
                                        <i class="ni ni-fat-remove"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted">{{ __('No sessions yet. Add a start and end date/time above.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endisset
    </div>
</div>
