@extends('general.index', $setup)
@section('cardbody')
<form action="{{ $setup['action'] }}" method="POST" enctype="multipart/form-data">
    @csrf
    @isset($setup['isupdate'])
        @method('PUT')
    @endisset
    @isset($setup['inrow'])
        <div class="row">
    @endisset
        @include('partials.fields',['fiedls'=>$fields])
    @isset($setup['inrow'])
        </div>
    @endisset
    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
</form>

<div class="card mt-4">
    <div class="card-header">
        <h3 class="mb-0">{{ __('Occurrences') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('reminders.events.occurrences.store', ['event' => $event->id]) }}" class="row g-3">
            @csrf
            <div class="col-md-3">
                <label class="form-control-label">{{ __('Starts at') }}</label>
                <input type="datetime-local" name="starts_at" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-control-label">{{ __('Ends at') }}</label>
                <input type="datetime-local" name="ends_at" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-control-label">{{ __('Capacity') }}</label>
                <input type="number" name="capacity" class="form-control" value="50" min="1" required>
            </div>
            <div class="col-md-2">
                <label class="form-control-label">{{ __('Status') }}</label>
                <select name="status" class="form-control">
                    <option value="draft">{{ __('Draft') }}</option>
                    <option value="published">{{ __('Published') }}</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">{{ __('Add occurrence') }}</button>
            </div>
        </form>

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
                    @forelse ($event->occurrences as $occurrence)
                        <tr>
                            <td>{{ $occurrence->starts_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $occurrence->ends_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $occurrence->capacity }}</td>
                            <td>{{ $occurrence->seatsRemaining() }}</td>
                            <td><span class="badge badge-default">{{ ucfirst($occurrence->status) }}</span></td>
                            <td>
                                <a href="{{ route('reminders.events.occurrences.delete', ['event' => $event->id, 'occurrence' => $occurrence->id]) }}" class="btn btn-danger btn-sm">
                                    <i class="ni ni-fat-remove"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted">{{ __('No occurrences yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
