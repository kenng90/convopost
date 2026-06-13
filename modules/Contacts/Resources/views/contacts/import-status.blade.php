@extends('layouts.app', ['title' => __('Contact import status')])

@section('content')
    <div class="header pb-8 pt-5 pt-md-8"></div>
    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h3 class="mb-0">{{ __('Contact import status') }}</h3>
                                <p class="text-muted mb-0 small">{{ $contactImport->original_filename }}</p>
                            </div>
                            <div class="col-4 text-right">
                                <a href="{{ route('contacts.index') }}" class="btn btn-sm btn-primary">{{ __('Back to contacts') }}</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        @include('partials.flash')
                    </div>

                    <div class="card-body">
                        <div id="import-status-alert" class="alert d-none" role="alert"></div>

                        @if ($contactImport->warnings)
                            <div class="alert alert-warning">{{ $contactImport->warnings }}</div>
                        @endif

                        @if ($contactImport->isStalePending())
                            <div id="import-stale-pending-alert" class="alert alert-warning">
                                {{ __('This import is still waiting to start. A queue worker is required. Ask your administrator to run: php artisan queue:work --timeout=3600') }}
                            </div>
                        @else
                            <div id="import-stale-pending-alert" class="alert alert-warning d-none"></div>
                        @endif

                        <p class="text-muted small mb-4">
                            {{ __('This page updates automatically. You can also check progress from Contacts or Import history.') }}
                            <a href="{{ route('contacts.import.history') }}">{{ __('Import history') }}</a>
                        </p>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span id="import-status-label">{{ $contactImport->displayStatus() }}</span>
                                <span id="import-progress-text">{{ $contactImport->progressPercent() }}%</span>
                            </div>
                            <div class="progress">
                                <div id="import-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                     role="progressbar"
                                     style="width: {{ $contactImport->progressPercent() }}%"
                                     aria-valuenow="{{ $contactImport->progressPercent() }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100"></div>
                            </div>
                        </div>

                        <div class="row text-center">
                            <div class="col-md-3">
                                <h4 id="import-total-rows" class="mb-0">{{ number_format($contactImport->total_rows) }}</h4>
                                <small class="text-muted">{{ __('Total rows') }}</small>
                            </div>
                            <div class="col-md-3">
                                <h4 id="import-created-count" class="mb-0">{{ number_format($contactImport->created_count) }}</h4>
                                <small class="text-muted">{{ __('Created') }}</small>
                            </div>
                            <div class="col-md-3">
                                <h4 id="import-updated-count" class="mb-0">{{ number_format($contactImport->updated_count) }}</h4>
                                <small class="text-muted">{{ __('Updated') }}</small>
                            </div>
                            <div class="col-md-3">
                                <h4 id="import-skipped-count" class="mb-0">{{ number_format($contactImport->skipped_count) }}</h4>
                                <small class="text-muted">{{ __('Skipped') }}</small>
                            </div>
                        </div>

                        <p id="import-error-message" class="text-danger mt-4 mb-0 @if(!$contactImport->error_message) d-none @endif">
                            {{ $contactImport->error_message }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @include('layouts.footers.auth')
    </div>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var statusUrl = @json(route('contacts.import.status', $contactImport));
            var isFinished = @json($contactImport->isFinished());
            var pollIntervalMs = 3000;

            var statusLabel = document.getElementById('import-status-label');
            var progressText = document.getElementById('import-progress-text');
            var progressBar = document.getElementById('import-progress-bar');
            var totalRows = document.getElementById('import-total-rows');
            var createdCount = document.getElementById('import-created-count');
            var updatedCount = document.getElementById('import-updated-count');
            var skippedCount = document.getElementById('import-skipped-count');
            var errorMessage = document.getElementById('import-error-message');
            var statusAlert = document.getElementById('import-status-alert');

            function formatNumber(value) {
                return new Intl.NumberFormat().format(value || 0);
            }

            function updateStatus(data) {
                statusLabel.textContent = data.display_status || (data.status.charAt(0).toUpperCase() + data.status.slice(1));
                progressText.textContent = data.progress_percent + '%';
                progressBar.style.width = data.progress_percent + '%';
                progressBar.setAttribute('aria-valuenow', data.progress_percent);
                totalRows.textContent = formatNumber(data.total_rows);
                createdCount.textContent = formatNumber(data.created_count);
                updatedCount.textContent = formatNumber(data.updated_count);
                skippedCount.textContent = formatNumber(data.skipped_count);

                if (data.error_message) {
                    errorMessage.textContent = data.error_message;
                    errorMessage.classList.remove('d-none');
                }

                if (data.is_stale_pending) {
                    var staleAlert = document.getElementById('import-stale-pending-alert');
                    if (staleAlert) {
                        staleAlert.classList.remove('d-none');
                    }
                }

                if (data.status === 'completed' || data.is_finished) {
                    progressBar.classList.remove('progress-bar-animated');
                    statusAlert.className = 'alert alert-success';
                    statusAlert.textContent = @json(__('Import completed successfully.'));
                    statusAlert.classList.remove('d-none');
                }

                if (data.status === 'failed') {
                    progressBar.classList.remove('progress-bar-animated');
                    progressBar.classList.remove('bg-success');
                    progressBar.classList.add('bg-danger');
                    statusAlert.className = 'alert alert-danger';
                    statusAlert.textContent = @json(__('Import failed.'));
                    statusAlert.classList.remove('d-none');
                }
            }

            function pollStatus() {
                fetch(statusUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        updateStatus(data);

                        if (!data.is_finished) {
                            window.setTimeout(pollStatus, pollIntervalMs);
                        }
                    })
                    .catch(function () {
                        window.setTimeout(pollStatus, pollIntervalMs);
                    });
            }

            if (!isFinished) {
                pollStatus();
            }
        });
    </script>
@endsection
