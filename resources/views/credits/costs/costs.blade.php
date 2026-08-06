@extends('layouts.app', ['title' => __('Credits')])

@section('content')
    <div class="header pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <h1 class="mb-3 mt--3">💰 {{ __('Credits') }}</h1>
                <p class="text-muted mb-0">{{ __('Configure how many credits each billable action consumes. Set to 0 for free actions.') }}</p>
            </div>
        </div>
    </div>

    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col">
                @include('partials.flash')

                <div class="card shadow">
                    <div class="card-header border-0">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h3 class="mb-0">{{ __('Costs per action') }}</h3>
                            </div>
                            <div class="col-4 text-right">
                                <a href="{{ route('credits.create') }}" class="btn btn-sm btn-success">
                                    {{ __('Grant credits') }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('credits.costs') }}">
                            @csrf

                            @if (count($actions) === 0)
                                <div class="text-center">
                                    <p>{{ __('No billable actions are registered. Run php artisan credits:sync-actions') }}</p>
                                </div>
                            @else
                                @php
                                    $grouped = collect($actions)->groupBy('category');
                                @endphp

                                @foreach ($grouped as $category => $categoryActions)
                                    <h4 class="mt-4 mb-3">
                                        {{ $categories[$category] ?? ucfirst($category) }}
                                    </h4>

                                    <div class="table-responsive">
                                        <table class="table table-striped align-items-center">
                                            <thead>
                                                <tr>
                                                    <th style="width: 34%">{{ __('Action') }}</th>
                                                    <th style="width: 26%">{{ __('Module') }}</th>
                                                    <th style="width: 18%">{{ __('Billing type') }}</th>
                                                    <th style="width: 12%">{{ __('Credits') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($categoryActions as $action)
                                                    <tr>
                                                        <td>
                                                            <div class="font-weight-bold">{{ $action['name'] }}</div>
                                                            @if (! empty($action['help']))
                                                                <small class="text-muted d-block">{{ $action['help'] }}</small>
                                                            @endif
                                                            <code class="small">{{ $action['action'] }}</code>
                                                        </td>
                                                        <td>{{ $action['module'] }}</td>
                                                        <td>
                                                            <select
                                                                class="form-control action_type custom-select"
                                                                name="costs[{{ $action['action'] }}][type]"
                                                            >
                                                                <option value="1" @selected(! $action['is_usage_based'])>
                                                                    {{ __('Fixed amount') }}
                                                                </option>
                                                                <option value="-1" @selected($action['is_usage_based'])>
                                                                    {{ __('Usage based') }}
                                                                </option>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input
                                                                type="number"
                                                                min="0"
                                                                step="1"
                                                                name="costs[{{ $action['action'] }}][cost]"
                                                                class="form-control action_cost"
                                                                value="{{ $action['is_usage_based'] ? '' : (int) $action['cost'] }}"
                                                                @if ($action['is_usage_based']) style="display:none" @endif
                                                            >
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endforeach

                                <div class="text-right mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save mr-2"></i>{{ __('Save changes') }}
                                    </button>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script type="text/javascript">
        $(document).ready(function() {
            $('select.action_type').on('change', function() {
                const costInput = $(this).closest('tr').find('input.action_cost');
                if ($(this).val() === '-1') {
                    costInput.hide();
                } else {
                    costInput.show();
                }
            });
        });
    </script>
@endsection
