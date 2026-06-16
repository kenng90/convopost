@extends('layouts.app', ['title' => __('Journey')])
@section('js')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jkanban@1.3.1/dist/jkanban.min.css">
    <style>
        #myKanban {
            overflow-x: auto;
            padding: 20px 0;
        }

        .kanban-board.info {
            background: #4b5563;
            border-radius: 6px;
        }

        .dark-mode .kanban-board.info,
        body.dark-version .kanban-board.info {
            background: #374151;
        }

        .kanban-title-board {
            color: white;
        }

        .kanban-item {
            cursor: grab;
        }

        .kanban-item a.contact-card-link {
            color: inherit;
            text-decoration: none;
            display: block;
            width: 100%;
        }

        .kanban-item a.contact-card-link:hover {
            text-decoration: underline;
        }

        .stage-meta {
            font-size: 11px;
            opacity: 0.85;
            margin-top: 4px;
        }

        .contact-search-results {
            max-height: 240px;
            overflow-y: auto;
        }
    </style>
@endsection

@section('content')
<div class="header pb-8 pt-2 pt-md-7">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row">
                <div class="col">
                    <h1 class="mb-1">📋 {{ $journey->name }}</h1>
                    @if($journey->description)
                        <p class="text-muted mb-3">{{ $journey->description }}</p>
                    @endif
                </div>
                <div class="col-auto">
                    <a href="{{ route('journies.index') }}" class="btn btn-sm btn-neutral">{{ __('Back to Journeys') }}</a>
                    <a href="{{ route('journies.analytics', ['journey_id' => $journey->id]) }}" class="btn btn-sm btn-info">{{ __('Analytics') }}</a>
                    <a href="{{ route('journies.group-rules', $journey) }}" class="btn btn-sm btn-warning">{{ __('Group rules') }}</a>
                    <a href="{{ route('stages.create', $journey) }}" class="btn btn-sm btn-primary">
                        <i class="ni ni-fat-add"></i> {{ __('Add stage') }}
                    </a>
                    <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#addContactModal">
                        <i class="ni ni-single-02"></i> {{ __('Add contact') }}
                    </button>
                </div>
            </div>

            <div class="row align-items-center pt-2">
                <div class="col-12">
                    @include('partials.flash')
                </div>

                <div class="modal fade" id="addContactModal" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{ __('Add contact to journey') }}</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="contact_search">{{ __('Search contacts') }}</label>
                                    <input type="text" id="contact_search" class="form-control" placeholder="{{ __('Search by name or phone...') }}">
                                </div>
                                <div id="contact_search_results" class="list-group contact-search-results"></div>
                                <input type="hidden" id="selected_contact_id">
                                <div id="selected_contact_label" class="mt-2 text-muted small"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                <button type="button" id="add_contact_submit" class="btn btn-primary" disabled>{{ __('Add contact') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div id="myKanban"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('journies::scripts')
@endsection
