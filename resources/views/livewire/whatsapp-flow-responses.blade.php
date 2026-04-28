<div class="container-fluid pt-5" wire:key="whatsapp-flow-responses">
    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2 d-flex align-items-center">
                <i class="ni ni-folder-statistics text-primary mr-2"></i>
                WhatsApp Flow Responses
            </h1>
            <p class="text-muted">View and analyze form submissions from WhatsApp flows</p>
        </div>
        <div class="col-md-4 text-right">
            <a href="{{ route('whatsapp-flows.index') }}" class="btn btn-secondary btn-sm">
                <i class="ni ni-fat-remove mr-1"></i>
                Back to Flows
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted d-block">Total Responses</small>
                            <h3 class="mb-0">{{ $totalResponses }}</h3>
                        </div>
                        <div style="font-size: 32px; opacity: 0.3;">📨</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted d-block">Completed</small>
                            <h3 class="mb-0 text-success">{{ $completedResponses }}</h3>
                        </div>
                        <div style="font-size: 32px; opacity: 0.3;">✓</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted d-block">Abandoned</small>
                            <h3 class="mb-0 text-warning">{{ $abandonedResponses }}</h3>
                        </div>
                        <div style="font-size: 32px; opacity: 0.3;">✕</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted d-block">Completion Rate</small>
                            <h3 class="mb-0">{{ $completionRate }}%</h3>
                        </div>
                        <div style="font-size: 32px; opacity: 0.3;">📊</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="row mb-4">
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
                </div>
            </div>
        </div>
    </div>

    {{-- Responses Table --}}
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                @if ($responses->isEmpty())
                    <div class="card-body text-center py-5 text-muted">
                        <i class="ni ni-folder-17" style="font-size: 48px; opacity: 0.3;"></i>
                        <h5 class="mt-3">No responses found</h5>
                        <p>No WhatsApp flow submissions yet. Start sending flows to contacts to see responses here.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Contact</th>
                                    <th>Flow</th>
                                    <th>Status</th>
                                    <th>Sent</th>
                                    <th>Completed</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($responses as $response)
                                    <tr>
                                        <td>
                                            <strong>{{ $response->contact_name ?? 'Unknown' }}</strong><br>
                                            <small class="text-muted">{{ $response->contact_phone }}</small>
                                        </td>
                                        <td>
                                            @if ($response->whatsappFlow)
                                                <strong>{{ $response->whatsappFlow->name }}</strong>
                                            @else
                                                <small class="text-muted">—</small>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $statusBadge = match($response->status) {
                                                    'completed' => 'badge-success',
                                                    'abandoned'  => 'badge-warning',
                                                    'failed'  => 'badge-danger',
                                                    'pending'  => 'badge-info',
                                                    default     => 'badge-secondary',
                                                };
                                            @endphp
                                            <span class="badge {{ $statusBadge }}">{{ ucfirst($response->status) }}</span>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $response->sent_at?->format('M d, H:i') ?? '—' }}</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $response->completed_at?->format('M d, H:i') ?? '—' }}</small>
                                        </td>
                                        <td class="text-right">
                                            @if ($response->status === 'completed' && !empty($response->responses))
                                            <button
    wire:click="viewResponse({{ $response->id }})"
    wire:loading.class="disabled"
    wire:target="viewResponse({{ $response->id }})"
    class="btn btn-primary btn-sm"
    title="View Response Details"
>
    <span wire:loading.remove wire:target="viewResponse({{ $response->id }})">
        <i class="ni ni-zoom-split-in"></i> View
    </span>
    <span wire:loading wire:target="viewResponse({{ $response->id }})">
        <span class="spinner-border spinner-border-sm me-1"></span> Loading...
    </span>
</button>
                                            @else
                                                <button
                                                    wire:click="viewResponse({{ $response->id }})"
                                                    class="btn btn-secondary btn-sm"
                                                    title="View Response Details"
                                                >
                                                    <i class="ni ni-zoom-split-in"></i> View
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($responses->hasPages())
                        <div class="card-body">
                            {{ $responses->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- View Response Modal --}}

@if ($selectedResponse || $loadingResponse)
    <div class="modal d-block" style="background: rgba(0,0,0,0.6); z-index: 1050;" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered" style="max-height: 92vh;">
            <div class="modal-content" style="max-height: 92vh; display: flex; flex-direction: column;">

                {{-- Loading State --}}
                @if ($loadingResponse)
                    <div class="modal-body text-center py-5 flex-grow-1 d-flex align-items-center justify-content-center">
                        <div>
                            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
                            <h5 class="mb-1">Loading Response...</h5>
                            <p class="text-muted">Fetching data and field labels</p>
                        </div>
                    </div>
                @else
                    {{-- HEADER - Always visible --}}
                    <div class="modal-header border-bottom">
                        <div>
                            <h5 class="modal-title mb-0">Response Details</h5>
                            <small class="text-muted">
                                {{ $selectedResponse['contact_name'] ?? 'Unknown' }} — 
                                {{ $selectedResponse['contact_phone'] ?? '' }}
                            </small>
                        </div>
                        <button 
    type="button" 
    class="close fs-4"
    style="margin-top: -4px;"
    wire:click="closeViewResponse"
    wire:loading.attr="disabled"
>
    <span aria-hidden="true">&times;</span>
</button>
                    </div>

                    {{-- BODY - Scrollable --}}
                    <div class="modal-body" style="overflow-y: auto; flex: 1 1 auto; min-height: 0;">
                        <!-- Metadata -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="small text-muted mb-1">Flow</p>
                                <p class="font-weight-600">{{ $selectedResponse['flow_name'] ?? '' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="small text-muted mb-1">Status</p>
                                @php
                                    $badge = match($selectedResponse['status'] ?? '') {
                                        'completed' => 'badge-success',
                                        'abandoned' => 'badge-warning',
                                        'failed'    => 'badge-danger',
                                        'pending'   => 'badge-info',
                                        default     => 'badge-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $badge }}">{{ ucfirst($selectedResponse['status'] ?? 'unknown') }}</span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="small text-muted mb-1">Sent At</p>
                                <p>{{ $selectedResponse['sent_at'] ? \Carbon\Carbon::parse($selectedResponse['sent_at'])->format('M d, Y H:i') : '—' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="small text-muted mb-1">Completed At</p>
                                <p>{{ $selectedResponse['completed_at'] ? \Carbon\Carbon::parse($selectedResponse['completed_at'])->format('M d, Y H:i') : '—' }}</p>
                            </div>
                        </div>

                        <hr>

                        <h6 class="font-weight-600 mb-3">Form Responses</h6>

                        <div class="responses-scroll-area" style="max-height: 380px; overflow-y: auto; padding-right: 12px;">
                            @if (!empty($selectedResponse['responses_with_labels']))
                                @foreach ($selectedResponse['responses_with_labels'] as $item)
                                    <div class="card border-0 bg-light p-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <p class="small text-muted mb-1 font-weight-600">
                                                    {{ $item['label'] }}
                                                </p>
                                                @if ($item['screen'])
                                                    <small class="text-xs text-gray-500">— {{ $item['screen'] }}</small>
                                                @endif
                                            </div>
                                            <small class="font-mono text-xs text-gray-400">{{ $item['key'] }}</small>
                                        </div>
                                        
                                        <p class="font-weight-600 text-break mb-0">
                                            @if (is_array($item['value']))
                                                {{ implode(', ', $item['value']) }}
                                            @elseif (is_bool($item['value']))
                                                {{ $item['value'] ? '✅ Yes' : '❌ No' }}
                                            @else
                                                {{ $item['value'] ?: '—' }}
                                            @endif
                                        </p>
                                    </div>
                                @endforeach
                            @elseif (!empty($selectedResponse['responses_missing']))
                                <div class="alert alert-warning">
                                    <strong>No form answers received from Meta.</strong><br>
                                    <small>Add input fields in Flow Builder and Re-publish.</small>
                                </div>
                            @else
                                <div class="alert alert-info">
                                    <i class="ni ni-notification-70 mr-1"></i>
                                    No form responses yet.
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- FOOTER --}}
                    <div class="modal-footer">
                        <button 
                            type="button" 
                            class="btn btn-secondary btn-sm"
                            wire:click="closeViewResponse"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove>Close</span>
                            <span wire:loading>
                                <span class="spinner-border spinner-border-sm me-1"></span>Closing...
                            </span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
</div>
