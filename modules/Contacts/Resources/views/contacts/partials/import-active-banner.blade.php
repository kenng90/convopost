@if ($activeImports->isNotEmpty())
    <div class="card-body pb-0" id="contact-import-active-banner" data-poll-url="{{ route('contacts.import.active') }}">
        @foreach ($activeImports as $activeImport)
            <div class="alert alert-info contact-import-active-item mb-3" data-import-id="{{ $activeImport->id }}">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div>
                        <strong>{{ __('Import in progress') }}:</strong>
                        <span class="contact-import-filename">{{ $activeImport->original_filename }}</span>
                        <span class="badge badge-default contact-import-status ml-2">{{ ucfirst($activeImport->status) }}</span>
                    </div>
                    <a href="{{ route('contacts.import.show', $activeImport) }}" class="btn btn-sm btn-primary">
                        {{ __('View progress') }}
                    </a>
                </div>
                <div class="progress mt-3 mb-2" style="height: 8px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary contact-import-progress-bar"
                         role="progressbar"
                         style="width: {{ $activeImport->progressPercent() }}%"
                         aria-valuenow="{{ $activeImport->progressPercent() }}"
                         aria-valuemin="0"
                         aria-valuemax="100"></div>
                </div>
                <small class="text-muted contact-import-progress-text">
                    {{ $activeImport->progressPercent() }}% —
                    <span class="contact-import-processed">{{ number_format($activeImport->processed_rows) }}</span>
                    /
                    <span class="contact-import-total">{{ number_format($activeImport->total_rows) }}</span>
                    {{ __('rows processed') }}
                </small>
                @if ($activeImport->isStalePending())
                    <div class="alert alert-warning mt-3 mb-0 contact-import-stale-warning">
                        {{ __('This import is still waiting to start. Make sure a queue worker is running: php artisan queue:work --timeout=3600') }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif

@isset($recentImports)
    @if ($recentImports->isNotEmpty())
        <div class="card-body pt-0">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="mb-0">{{ __('Recent imports') }}</h4>
                <a href="{{ route('contacts.import.history') }}" class="btn btn-sm btn-outline-primary">{{ __('View all') }}</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-items-center">
                    <thead>
                        <tr>
                            <th>{{ __('File') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Progress') }}</th>
                            <th>{{ __('Started') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentImports as $recentImport)
                            <tr>
                                <td>{{ $recentImport->original_filename }}</td>
                                <td>{{ ucfirst($recentImport->status) }}</td>
                                <td>{{ $recentImport->progressPercent() }}%</td>
                                <td>{{ $recentImport->created_at?->diffForHumans() }}</td>
                                <td class="text-right">
                                    <a href="{{ route('contacts.import.show', $recentImport) }}" class="btn btn-sm btn-outline-secondary">
                                        {{ __('Details') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endisset

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var banner = document.getElementById('contact-import-active-banner');

            if (!banner) {
                return;
            }

            var pollUrl = banner.getAttribute('data-poll-url');
            var pollIntervalMs = 5000;

            function updateBanner(imports) {
                if (!imports.length) {
                    banner.remove();
                    return;
                }

                imports.forEach(function (importData) {
                    var item = banner.querySelector('[data-import-id="' + importData.id + '"]');

                    if (!item) {
                        window.location.reload();
                        return;
                    }

                    var progressBar = item.querySelector('.contact-import-progress-bar');
                    var progressText = item.querySelector('.contact-import-progress-text');
                    var statusBadge = item.querySelector('.contact-import-status');
                    var staleWarning = item.querySelector('.contact-import-stale-warning');

                    if (progressBar) {
                        progressBar.style.width = importData.progress_percent + '%';
                        progressBar.setAttribute('aria-valuenow', importData.progress_percent);
                    }

                    if (progressText) {
                        item.querySelector('.contact-import-processed').textContent = new Intl.NumberFormat().format(importData.processed_rows || 0);
                        item.querySelector('.contact-import-total').textContent = new Intl.NumberFormat().format(importData.total_rows || 0);
                        progressText.childNodes[0].textContent = importData.progress_percent + '% — ';
                    }

                    if (statusBadge) {
                        statusBadge.textContent = importData.status.charAt(0).toUpperCase() + importData.status.slice(1);
                    }

                    if (importData.is_stale_pending && !staleWarning) {
                        var warning = document.createElement('div');
                        warning.className = 'alert alert-warning mt-3 mb-0 contact-import-stale-warning';
                        warning.textContent = @json(__('This import is still waiting to start. Make sure a queue worker is running: php artisan queue:work --timeout=3600'));
                        item.appendChild(warning);
                    }
                });
            }

            function pollActiveImports() {
                fetch(pollUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        updateBanner(data.imports || []);

                        if ((data.imports || []).length > 0) {
                            window.setTimeout(pollActiveImports, pollIntervalMs);
                        }
                    })
                    .catch(function () {
                        window.setTimeout(pollActiveImports, pollIntervalMs);
                    });
            }

            pollActiveImports();
        });
    </script>
@endpush
