<div class="container-fluid pt-5" wire:key="whatsapp-flow-responses">
    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2 d-flex align-items-center">
                <i class="ni ni-folder-statistics text-primary mr-2"></i>
                WhatsApp Flow Responses
            </h1>
            <p class="text-muted mb-0">
                @if ($selectedFlow)
                    Viewing submissions for <strong>{{ $selectedFlow->name }}</strong>
                @else
                    View and analyze form submissions from WhatsApp flows
                @endif
            </p>
        </div>
        <div class="col-md-4 text-right d-flex justify-content-end align-items-start" style="gap: 0.5rem;">
            @if ($exportUrl)
                <a href="{{ $exportUrl }}" class="btn btn-outline-primary btn-sm">
                    <i class="ni ni-cloud-download-95 mr-1"></i> Export CSV
                </a>
            @endif
            <a href="{{ route('whatsapp-flows.index') }}" class="btn btn-secondary btn-sm">
                <i class="ni ni-fat-remove mr-1"></i> Back to Flows
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Total</small>
                    <h3 class="mb-0">{{ $totalResponses }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Completed</small>
                    <h3 class="mb-0 text-success">{{ $completedResponses }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Abandoned</small>
                    <h3 class="mb-0 text-warning">{{ $abandonedResponses }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Completion Rate</small>
                    <h3 class="mb-0">{{ $completionRate }}%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Avg. Time</small>
                    <h3 class="mb-0">{{ $selectedFlow ? $avgCompletionLabel : '—' }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Fields</small>
                    <h3 class="mb-0">{{ $selectedFlow ? count($fieldColumns) : '—' }}</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="small font-weight-600">Filter by Flow</label>
                            <select wire:model.live="flowId" class="form-control form-control-sm">
                                <option value="">All Flows</option>
                                @foreach ($flows as $flow)
                                    <option value="{{ $flow->id }}">{{ $flow->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small font-weight-600">Filter by Status</label>
                            <select wire:model.live="statusFilter" class="form-control form-control-sm">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="abandoned">Abandoned</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small font-weight-600">From Date</label>
                            <input type="date" wire:model.live="dateFrom" class="form-control form-control-sm"/>
                        </div>
                        <div class="col-md-3">
                            <label class="small font-weight-600">To Date</label>
                            <input type="date" wire:model.live="dateTo" class="form-control form-control-sm"/>
                        </div>
                    </div>

                    @if ($analyticsFieldFilter && $analyticsValueFilter)
                        <div class="mt-3 d-flex align-items-center flex-wrap" style="gap: 0.5rem;">
                            <span class="badge badge-primary px-3 py-2">
                                Filtered: {{ $analyticsValueFilter }}
                            </span>
                            <button wire:click="clearAnalyticsFilter" class="btn btn-link btn-sm p-0">
                                Clear filter
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($selectedFlow)
        <ul class="nav nav-pills mb-3">
            <li class="nav-item">
                <button
                    wire:click="setActiveTab('submissions')"
                    class="nav-link {{ $activeTab === 'submissions' ? 'active' : '' }}"
                    type="button"
                >
                    Submissions
                </button>
            </li>
            <li class="nav-item">
                <button
                    wire:click="setActiveTab('analytics')"
                    class="nav-link {{ $activeTab === 'analytics' ? 'active' : '' }}"
                    type="button"
                >
                    Analytics
                </button>
            </li>
        </ul>
    @endif

    {{-- Analytics Tab --}}
    @if ($selectedFlow && $activeTab === 'analytics')
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 font-weight-600">Completion Funnel</h6>
                        <small class="text-muted">How far contacts progressed through the flow</small>
                    </div>
                    <div class="card-body">
                        @forelse ($funnelAnalytics as $step)
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="font-weight-600">{{ $step['label'] }}</span>
                                    <span class="text-muted">{{ $step['count'] }} ({{ $step['percent'] }}%)</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div
                                        class="progress-bar bg-primary"
                                        role="progressbar"
                                        style="width: {{ $step['percent'] }}%"
                                    ></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No funnel data yet for this flow.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 font-weight-600">Choice Field Breakdown</h6>
                        <small class="text-muted">Click a bar to filter submissions</small>
                    </div>
                    <div class="card-body" style="max-height: 480px; overflow-y: auto;">
                        @forelse ($choiceAnalytics as $field)
                            <div class="mb-4">
                                <p class="small font-weight-600 mb-2">{{ $field['label'] }}</p>
                                @foreach ($field['options'] as $option)
                                    <button
                                        type="button"
                                        wire:click="filterByChoice(@js($field['field_key']), @js($option['label']))"
                                        class="btn btn-link btn-block text-left p-0 mb-2"
                                    >
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span>{{ $option['label'] }}</span>
                                            <span class="text-muted">{{ $option['count'] }} ({{ $option['percent'] }}%)</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div
                                                class="progress-bar bg-success"
                                                role="progressbar"
                                                style="width: {{ $option['percent'] }}%"
                                            ></div>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @empty
                            <p class="text-muted mb-0">No choice-field responses yet. Analytics appear for radio, dropdown, checkbox, and chips fields.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Submissions Tab / Default View --}}
    @if (! $selectedFlow || $activeTab === 'submissions')
        <div class="row">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                    @if ($responses->isEmpty())
                        <div class="card-body text-center py-5 text-muted">
                            <i class="ni ni-folder-17" style="font-size: 48px; opacity: 0.3;"></i>
                            <h5 class="mt-3">No responses found</h5>
                            <p class="mb-0">
                                @if ($selectedFlow)
                                    No submissions yet for <strong>{{ $selectedFlow->name }}</strong>. Share this flow via campaign or automation.
                                @else
                                    No WhatsApp flow submissions yet. Select a flow or start sending flows to contacts.
                                @endif
                            </p>
                        </div>
                    @elseif ($selectedFlow && ! empty($fieldColumns))
                        {{-- Flow-specific dynamic table --}}
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="position: sticky; left: 0; background: #f8f9fa; z-index: 1;">Contact</th>
                                        <th>Phone</th>
                                        <th>Submitted</th>
                                        @foreach ($fieldColumns as $column)
                                            <th>{{ $column['label'] }}</th>
                                        @endforeach
                                        <th>Status</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tableRows as $row)
                                        <tr>
                                            <td style="position: sticky; left: 0; background: #fff; z-index: 1;">
                                                <strong>{{ $row['contact_name'] }}</strong>
                                            </td>
                                            <td><small class="text-muted">{{ $row['contact_phone'] ?? '—' }}</small></td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $row['completed_at']?->format('M d, H:i') ?? $row['sent_at']?->format('M d, H:i') ?? '—' }}
                                                </small>
                                            </td>
                                            @foreach ($fieldColumns as $column)
                                                <td>
                                                    <small>{{ $row['cells'][$column['key']] ?? '—' }}</small>
                                                </td>
                                            @endforeach
                                            <td>
                                                @php
                                                    $statusBadge = match($row['status']) {
                                                        'completed' => 'badge-success',
                                                        'abandoned' => 'badge-warning',
                                                        'failed' => 'badge-danger',
                                                        'pending' => 'badge-info',
                                                        default => 'badge-secondary',
                                                    };
                                                @endphp
                                                <span class="badge {{ $statusBadge }}">{{ ucfirst($row['status']) }}</span>
                                            </td>
                                            <td class="text-right">
                                                <a
                                                    wire:click="viewResponse({{ $row['id'] }})"
                                                    wire:loading.class="disabled"
                                                    wire:target="viewResponse({{ $row['id'] }})"
                                                    class="btn btn-primary btn-sm"
                                                >
                                                    <i class="ni ni-zoom-split-in"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        {{-- Generic all-flows table with preview --}}
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Contact</th>
                                        <th>Flow</th>
                                        <th>Preview</th>
                                        <th>Status</th>
                                        <th>Sent</th>
                                        <th>Completed</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($genericRows as $row)
                                        <tr>
                                            <td>
                                                <strong>{{ $row['contact_name'] }}</strong><br>
                                                <small class="text-muted">{{ $row['contact_phone'] }}</small>
                                            </td>
                                            <td>
                                                @if ($row['flow_name'])
                                                    <strong>{{ $row['flow_name'] }}</strong>
                                                @else
                                                    <small class="text-muted">—</small>
                                                @endif
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $row['preview'] ?: '—' }}</small>
                                            </td>
                                            <td>
                                                @php
                                                    $statusBadge = match($row['status']) {
                                                        'completed' => 'badge-success',
                                                        'abandoned' => 'badge-warning',
                                                        'failed' => 'badge-danger',
                                                        'pending' => 'badge-info',
                                                        default => 'badge-secondary',
                                                    };
                                                @endphp
                                                <span class="badge {{ $statusBadge }}">{{ ucfirst($row['status']) }}</span>
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $row['sent_at']?->format('M d, H:i') ?? '—' }}</small>
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $row['completed_at']?->format('M d, H:i') ?? '—' }}</small>
                                            </td>
                                            <td class="text-right">
                                                <a
                                                    wire:click="viewResponse({{ $row['id'] }})"
                                                    wire:loading.class="disabled"
                                                    wire:target="viewResponse({{ $row['id'] }})"
                                                    class="btn btn-primary btn-sm"
                                                >
                                                    <i class="ni ni-zoom-split-in"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($responses->hasPages())
                        <div class="card-body">
                            {{ $responses->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Slide-over detail panel --}}
    @if ($selectedResponse || $loadingResponse)
        <div
            class="position-fixed"
            style="inset: 0; z-index: 1040; background: rgba(0,0,0,0.45);"
            wire:click="closeViewResponse"
        ></div>

        <div
            class="position-fixed bg-white shadow-lg d-flex flex-column"
            style="top: 0; right: 0; width: min(480px, 100vw); height: 100vh; z-index: 1050;"
        >
            @if ($loadingResponse)
                <div class="flex-grow-1 d-flex align-items-center justify-content-center p-5 text-center">
                    <div>
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <h5 class="mb-1">Loading response...</h5>
                        <p class="text-muted mb-0">Fetching data and field labels</p>
                    </div>
                </div>
            @else
                <div class="border-bottom p-3 d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1">Response Details</h5>
                        <small class="text-muted">{{ $selectedResponse['flow_name'] ?? '' }}</small>
                    </div>
                    <button type="button" class="close" wire:click="closeViewResponse">
                        <span>&times;</span>
                    </button>
                </div>

                <div class="flex-grow-1 overflow-auto p-3">
                    {{-- Contact card --}}
                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center" style="gap: 0.75rem;">
                                <div
                                    class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center font-weight-bold"
                                    style="width: 40px; height: 40px; flex-shrink: 0;"
                                >
                                    {{ strtoupper(substr($selectedResponse['contact_name'] ?? '?', 0, 1)) }}
                                </div>
                                <div class="flex-grow-1" style="min-width: 0;">
                                    <p class="mb-0 font-weight-600">{{ $selectedResponse['contact_name'] ?? 'Unknown' }}</p>
                                    <small class="text-muted">{{ $selectedResponse['contact_phone'] ?? '' }}</small>
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-wrap" style="gap: 0.5rem;">
                                @if (! empty($selectedResponse['contact_id']))
                                    <a
                                        href="{{ route('contacts.edit', ['contact' => $selectedResponse['contact_id']]) }}"
                                        class="btn btn-outline-primary btn-sm"
                                    >
                                        View Contact
                                    </a>
                                @endif
                                <a href="{{ route('chat.index') }}" class="btn btn-outline-secondary btn-sm">
                                    Open Chat
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <p class="small text-muted mb-1">Status</p>
                            @php
                                $badge = match($selectedResponse['status'] ?? '') {
                                    'completed' => 'badge-success',
                                    'abandoned' => 'badge-warning',
                                    'failed' => 'badge-danger',
                                    'pending' => 'badge-info',
                                    default => 'badge-secondary',
                                };
                            @endphp
                            <span class="badge {{ $badge }}">{{ ucfirst($selectedResponse['status'] ?? 'unknown') }}</span>
                        </div>
                        <div class="col-6">
                            <p class="small text-muted mb-1">Time to complete</p>
                            <p class="mb-0">{{ $selectedResponse['duration_label'] ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <p class="small text-muted mb-1">Sent At</p>
                            <p class="mb-0 small">
                                {{ $selectedResponse['sent_at'] ? \Carbon\Carbon::parse($selectedResponse['sent_at'])->format('M d, Y H:i') : '—' }}
                            </p>
                        </div>
                        <div class="col-6">
                            <p class="small text-muted mb-1">Completed At</p>
                            <p class="mb-0 small">
                                {{ $selectedResponse['completed_at'] ? \Carbon\Carbon::parse($selectedResponse['completed_at'])->format('M d, Y H:i') : '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-weight-600 mb-0">Form Responses</h6>
                        <div class="custom-control custom-switch">
                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="showTechnicalFields"
                                wire:model.live="showTechnicalFields"
                            >
                            <label class="custom-control-label small" for="showTechnicalFields">Show field keys</label>
                        </div>
                    </div>

                    @if (! empty($selectedResponse['responses_by_screen']))
                        @foreach ($selectedResponse['responses_by_screen'] as $screen)
                            <div class="mb-3">
                                <p class="small text-uppercase text-muted font-weight-600 mb-2">
                                    {{ $screen['title'] }}
                                </p>
                                @foreach ($screen['fields'] as $item)
                                    <div class="card border-0 bg-light p-3 mb-2">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <p class="small text-muted mb-0 font-weight-600">{{ $item['label'] }}</p>
                                            @if ($showTechnicalFields)
                                                <small class="font-mono text-muted">{{ $item['key'] }}</small>
                                            @endif
                                        </div>

                                        @if (in_array($item['type'], ['radio', 'select', 'chips', 'checkbox'], true))
                                            <span class="badge badge-light border text-dark">{{ $item['display_value'] }}</span>
                                        @elseif ($item['type'] === 'optin')
                                            <span class="badge {{ $item['display_value'] === 'Yes' ? 'badge-success' : 'badge-secondary' }}">
                                                {{ $item['display_value'] }}
                                            </span>
                                        @elseif ($item['type'] === 'date')
                                            <p class="mb-0 font-weight-600">
                                                <i class="ni ni-calendar-grid-58 mr-1 text-muted"></i>{{ $item['display_value'] }}
                                            </p>
                                        @else
                                            <p class="font-weight-600 text-break mb-0">{{ $item['display_value'] }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    @elseif (! empty($selectedResponse['responses_missing']))
                        <div class="alert alert-warning">
                            <strong>No form answers received from Meta.</strong><br>
                            <small>Add input fields in Flow Builder and re-publish.</small>
                        </div>
                    @else
                        <div class="alert alert-info mb-0">
                            No form responses yet.
                        </div>
                    @endif
                </div>

                <div class="border-top p-3">
                    <button type="button" class="btn btn-secondary btn-sm btn-block" wire:click="closeViewResponse">
                        Close
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
