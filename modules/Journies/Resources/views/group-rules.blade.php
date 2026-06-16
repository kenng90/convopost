@extends('layouts.app', ['title' => __('Group rules')])

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="mb-0">{{ __('Group rules') }} — {{ $journey->name }}</h1>
                <p class="text-muted mb-0">{{ __('When a contact is added to a group, move them to the selected stage automatically.') }}</p>
            </div>
            <div class="col-auto">
                <a href="{{ route('journies.kanban', $journey) }}" class="btn btn-sm btn-neutral">{{ __('Back to kanban') }}</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt--7">
  @include('partials.flash')

  <div class="card shadow mb-4">
    <div class="card-header"><h3 class="mb-0">{{ __('Add rule') }}</h3></div>
    <div class="card-body">
      <form method="POST" action="{{ route('journies.group-rules.store', $journey) }}">
        @csrf
        <div class="row">
          <div class="col-md-5 form-group">
            <label for="group_id">{{ __('Contact group') }}</label>
            <select name="group_id" id="group_id" class="form-control" required>
              <option value="">{{ __('Select group') }}</option>
              @foreach($groups as $group)
                <option value="{{ $group->id }}">{{ $group->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-5 form-group">
            <label for="stage_id">{{ __('Move to stage') }}</label>
            <select name="stage_id" id="stage_id" class="form-control" required>
              @foreach($journey->stages as $stage)
                <option value="{{ $stage->id }}">{{ $stage->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end form-group">
            <button type="submit" class="btn btn-primary btn-block">{{ __('Save rule') }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow">
    <div class="card-header"><h3 class="mb-0">{{ __('Active rules') }}</h3></div>
    <div class="card-body table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>{{ __('Group') }}</th>
            <th>{{ __('Stage') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($journey->groupRules as $rule)
            <tr>
              <td>{{ $rule->group?->name }}</td>
              <td>{{ $rule->stage?->name }}</td>
              <td class="text-right">
                <form method="POST" action="{{ route('journies.group-rules.delete', [$journey, $rule]) }}" onsubmit="return confirm('{{ __('Delete this rule?') }}')">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-danger" type="submit">{{ __('Delete') }}</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="text-muted">{{ __('No group rules yet.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
