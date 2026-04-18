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
                                                    class="btn btn-primary btn-sm"
                                                    title="View Response Details"
                                                >
                                                    <i class="ni ni-zoom-split-in"></i> View
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
    @if ($selectedResponse)
        <div class="modal d-block" style="background: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">Response Details</h5>
                            <small class="text-muted">
                                {{ $selectedResponse['contact_name'] ?? 'Unknown' }} — {{ $selectedResponse['contact_phone'] }}
                            </small>
                        </div>
                        <button type="button" class="close" wire:click="closeViewResponse">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="small text-muted mb-1">Flow</p>
                                <p class="font-weight-600">{{ $selectedResponse['flow_name'] ?? ('Flow #' . $selectedResponse['whatsapp_flow_id']) }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="small text-muted mb-1">Status</p>
                                @php
                                    $badge = match($selectedResponse['status']) {
                                        'completed' => 'badge-success',
                                        'abandoned'  => 'badge-warning',
                                        'failed'     => 'badge-danger',
                                        default      => 'badge-info',
                                    };
                                @endphp
                                <span class="badge {{ $badge }}">{{ ucfirst($selectedResponse['status']) }}</span>
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

                        @if (!empty($selectedResponse['responses']))
                            @foreach ($selectedResponse['responses'] as $key => $value)
                                @php
                                    // Convert field keys like "text_1_name", "radio_2", "textarea_2" into readable labels
                                    // Strip type prefix + numeric ID: "textarea_2" → "", "text_1_full_name" → "full name"
                                    $stripped = preg_replace('/^(text|textarea|radio|checkbox|select|date|chips|optin|media|dropdown)_\d+_?/i', '', $key);
                                    // If nothing left after stripping, humanise the full key (e.g. "textarea_2" → "Textarea 2")
                                    $label = $stripped
                                        ? ucwords(str_replace('_', ' ', $stripped))
                                        : ucwords(str_replace('_', ' ', $key));
                                @endphp
                                <div class="card border-0 bg-light p-3 mb-2">
                                    <p class="small text-muted mb-1">{{ $label }}</p>
                                    <p class="font-weight-600 text-break mb-0">
                                        @if (is_array($value))
                                            {{ implode(', ', $value) }}
                                        @else
                                            {{ $value ?: '—' }}
                                        @endif
                                    </p>
                                </div>
                            @endforeach
                        @elseif (!empty($selectedResponse['responses_missing']))
                            <div class="alert alert-warning mb-0">
                                <strong>No form answers received from Meta.</strong><br>
                                <small>This happens when the published flow has no input fields (text, radio, dropdown, etc.), or the flow on Meta is outdated.
                                <br><strong>To fix:</strong> add input fields to your flow in the flow builder, then click <em>Re-publish</em> to push the updated version to Meta.</small>
                            </div>
                        @else
                            <div class="alert alert-info mb-0">
                                <i class="ni ni-notification-70 mr-1"></i>
                                No form responses yet — the contact hasn't submitted the form.
                            </div>
                        @endif

                        @if (!empty($selectedResponse['notes']))
                            <hr>
                            <h6 class="font-weight-600">Notes</h6>
                            <p class="text-muted">{{ $selectedResponse['notes'] }}</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="closeViewResponse">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
