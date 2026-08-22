@extends('layouts.app')

@section('admin_title')
    {{ __('Agency portfolio') }}
@endsection

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <h2 class="mb-1">{{ __('Agency portfolio') }}</h2>
            <p class="text-muted mb-4">{{ __('Roll up outcomes across every workspace you own, then clone playbooks to a client.') }}</p>

            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm"><div class="card-body">
                        <p class="text-muted mb-0">{{ __('Workspaces') }}</p>
                        <p class="display-4 mb-0">{{ $rollup['companies'] }}</p>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm"><div class="card-body">
                        <p class="text-muted mb-0">{{ __('Carts recovered') }}</p>
                        <p class="display-4 mb-0">{{ $rollup['recovered'] }}</p>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm"><div class="card-body">
                        <p class="text-muted mb-0">{{ __('Paid leads') }}</p>
                        <p class="display-4 mb-0">{{ $rollup['paid'] }}</p>
                    </div></div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header">{{ __('Workspaces') }}</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Recovered carts') }}</th>
                                <th>{{ __('Attendance %') }}</th>
                                <th>{{ __('Paid') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($companies as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['cart_recovered'] }}</td>
                                    <td>{{ $row['attendance_rate'] }}</td>
                                    <td>{{ $row['paid'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header">{{ __('Clone a playbook') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('agency.clone-playbook') }}" class="form-row align-items-end">
                        @csrf
                        <div class="col-md-3 mb-2">
                            <label>{{ __('From') }}</label>
                            <select name="from_company_id" class="form-control" required>
                                @foreach($companies as $row)
                                    <option value="{{ $row['id'] }}">{{ $row['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label>{{ __('To') }}</label>
                            <select name="to_company_id" class="form-control" required>
                                @foreach($companies as $row)
                                    <option value="{{ $row['id'] }}">{{ $row['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label>{{ __('Playbook') }}</label>
                            <select name="playbook" class="form-control" required>
                                @foreach($playbooks as $playbook)
                                    <option value="{{ $playbook }}">{{ $playbook }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <button class="btn btn-primary">{{ __('Clone') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
