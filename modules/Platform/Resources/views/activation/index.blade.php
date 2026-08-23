@extends('layouts.app')

@section('admin_title')
    {{ __('Get started') }}
@endsection

@section('content')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header border-0">
                            <h2 class="mb-0">{{ __('Get to your first reply in 15 minutes') }}</h2>
                            <p class="text-muted mb-0 mt-2">{{ __('Complete these steps to activate your WhatsApp workspace.') }}</p>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-sm font-weight-bold">{{ __('Progress') }}</span>
                                    <span class="text-sm text-muted">{{ $progress }}%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $progress }}%"></div>
                                </div>
                            </div>

                            <div class="list-group list-group-flush">
                                @foreach($steps as $step)
                                    <div class="list-group-item px-0 py-3 d-flex align-items-start gap-3">
                                        <div class="mt-1">
                                            @if($step['completed'])
                                                <span class="badge badge-success badge-pill"><i class="ni ni-check-bold"></i></span>
                                            @else
                                                <span class="badge badge-secondary badge-pill">{{ $loop->iteration }}</span>
                                            @endif
                                        </div>
                                        <div class="flex-grow-1">
                                            <h4 class="mb-1">{{ $step['title'] }} @if(!empty($step['optional']))<span class="badge badge-light">{{ __('Optional') }}</span>@endif</h4>
                                            <p class="text-muted mb-2 small">{{ $step['description'] }}</p>
                                            @if($step['key'] === 'vertical' && !$step['completed'])
                                                <form method="POST" action="{{ route('activation.vertical') }}" class="d-flex flex-wrap gap-2">
                                                    @csrf
                                                    <select name="vertical" class="form-control form-control-sm" style="max-width: 240px;" required>
                                                        @foreach($verticals as $key => $pack)
                                                            <option value="{{ $key }}">{{ $pack['name'] }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="hidden" name="install_playbook" value="1">
                                                    <input type="text" name="test_phone" class="form-control form-control-sm" style="max-width: 180px;" placeholder="{{ __('Test phone (optional)') }}">
                                                    <button class="btn btn-sm btn-primary">{{ __('Install pack') }}</button>
                                                </form>
                                            @elseif(!$step['completed'] && $step['action_route'] && $step['key'] !== 'vertical')
                                                <a href="{{ route($step['action_route']) }}" class="btn btn-sm btn-primary">
                                                    {{ $step['action_label'] }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if(!empty($launch['checklist'] ?? null))
                                <div class="mt-4 p-3 rounded border">
                                    <h4 class="mb-2">{{ __('Go-live checklist') }}</h4>
                                    <p class="text-muted small mb-3">{{ $launch['message'] ?? '' }}</p>
                                    <ul class="list-unstyled mb-0">
                                        @foreach($launch['checklist'] as $item)
                                            <li class="d-flex justify-content-between gap-3 py-1">
                                                <span>
                                                    <strong>{{ $item['label'] }}</strong>
                                                    <span class="text-muted small d-block">{{ $item['detail'] ?? '' }}</span>
                                                </span>
                                                <span class="badge badge-{{ ($item['status'] ?? '') === 'live' ? 'success' : (($item['status'] ?? '') === 'waiting_on_meta' ? 'warning' : 'light') }}">
                                                    {{ str_replace('_', ' ', $item['status'] ?? '') }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if(!empty($launch['waiting_on_meta']))
                                        <p class="small text-warning mb-0 mt-3">
                                            {{ __('Still waiting on Meta:') }} {{ implode(', ', $launch['waiting_on_meta']) }}
                                        </p>
                                    @endif
                                </div>
                            @endif

                            @php($canFinish = collect($steps)->reject(fn ($s) => !empty($s['optional']))->every(fn ($s) => $s['completed']))
                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <form method="POST" action="{{ route('activation.complete') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-success" @if(!$canFinish) disabled @endif>
                                        {{ __('Finish activation') }}
                                    </button>
                                </form>
                                @hasrole('owner')
                                <form method="POST" action="{{ route('activation.skip') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary">{{ __('Skip for now') }}</button>
                                </form>
                                @endhasrole
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
