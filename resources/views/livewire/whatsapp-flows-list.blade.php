<div class="container-fluid pt-5" wire:key="whatsapp-flows-list">

    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2 d-flex align-items-center">
                <i class="ni ni-chat-left-3 text-primary mr-2"></i>
                WhatsApp Forms
            </h1>
            <p class="text-muted small">Create, publish, and manage interactive WhatsApp forms</p>
        </div>
        <div class="col-md-4 text-right d-flex justify-content-end align-items-center" style="gap: 0.5rem;">
            <button
                wire:click="openMetaImport"
                class="btn btn-outline-primary btn-sm"
                title="Browse and import flows from your Meta WhatsApp account"
            >
                <i class="ni ni-cloud-download-95 mr-1"></i> Import from Meta
            </button>
            <button
                wire:click="syncStatuses"
                wire:loading.attr="disabled"
                class="btn btn-outline-secondary btn-sm"
                title="Fetch the latest status of all published flows from Meta"
            >
                <span wire:loading.remove wire:target="syncStatuses">
                    <i class="ni ni-refresh-02 mr-1"></i> Sync Statuses
                </span>
                <span wire:loading wire:target="syncStatuses">
                    Syncing...
                </span>
            </button>
            <a href="{{ route('whatsapp-flows.create') }}" class="btn btn-primary btn-sm">
                <i class="ni ni-fat-add mr-1"></i>
                New Flow
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <label class="small font-weight-600">Search</label>
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Search flows..."
                                class="form-control form-control-sm"
                            />
                        </div>
                        <div class="col-md-6">
                            <label class="small font-weight-600">Status</label>
                            <select
                                wire:model.live="statusFilter"
                                class="form-control form-control-sm"
                            >
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Flows Table --}}
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                @if ($flows->isEmpty())
                    <div class="card-body text-center py-5">
                        <i class="ni ni-folder-17 text-muted" style="font-size: 48px;"></i>
                        <h5 class="text-muted mt-3">No flows found</h5>
                        <p class="text-muted small mb-3">
                            @if ($search || $statusFilter)
                                Try adjusting your filters.
                            @else
                                Create your first WhatsApp flow to get started.
                            @endif
                        </p>
                        @if (! $search && ! $statusFilter)
                            <a href="{{ route('whatsapp-flows.create') }}" class="btn btn-primary btn-sm">
                                <i class="ni ni-fat-add mr-1"></i>
                                Create Flow
                            </a>
                        @endif
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Form</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Screens</th>
                                    <th>Submissions</th>
                                    <th>Meta ID</th>
                                    <th>Updated</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($flows as $flow)
                                    <tr>
                                        <td>
                                            <strong>{{ $flow->name }}</strong>
                                            @if ($flow->description)
                                                <br>
                                                <small class="text-muted">{{ Str::limit($flow->description, 50) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if (($flow->flow_source ?? 'local') === 'meta_linked')
                                                <span class="badge badge-info">Meta import</span>
                                                @if ($flow->meta_synced_at)
                                                    <small class="text-muted d-block">Synced {{ $flow->meta_synced_at->diffForHumans() }}</small>
                                                @endif
                                            @else
                                                <span class="badge badge-light border">Built here</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $lifecycle = $flow->getLifecycleLabel();
                                                $statusBadge = match ($lifecycle) {
                                                    'Live on WhatsApp' => 'badge-success',
                                                    'Saved' => 'badge-info',
                                                    'Archived' => 'badge-secondary',
                                                    default => 'badge-warning',
                                                };
                                            @endphp
                                            <span class="badge {{ $statusBadge }}">{{ $lifecycle }}</span>
                                        </td>
                                        <td>
                                            {{ (int) $flow->screens_count }}
                                        </td>
                                        <td>
                                            <a href="{{ route('whatsapp-flows.responses', ['flowId' => $flow->id]) }}" class="badge badge-primary">
                                                {{ (int) ($flow->submissions_count ?? 0) }}
                                            </a>
                                            @if (($flow->pending_count ?? 0) > 0)
                                                <small class="text-muted d-block">{{ (int) $flow->pending_count }} pending</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($flow->meta_flow_id)
                                                <small class="text-muted font-weight-500">{{ $flow->meta_flow_id }}</small>
                                            @else
                                                <small class="text-muted">—</small>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $flow->updated_at->diffForHumans() }}</small>
                                        </td>
                                        <td class="text-right">
                                            @if ($flow->meta_flow_id)
                                                <button
                                                    wire:click="openTestSend({{ $flow->id }})"
                                                    title="Send test"
                                                    class="btn btn-success btn-sm"
                                                    style="margin-right: 3px;"
                                                >
                                                    <i class="ni ni-send"></i> Test
                                                </button>

                                                <div class="btn-group" style="margin-right: 3px;">
                                                    <button type="button" class="btn btn-warning btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        <i class="ni ni-settings"></i> Automate
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a class="dropdown-item" href="{{ route('whatsapp-flows.use-in-automation', ['id' => $flow->id, 'recipe' => 'lead']) }}">Lead capture</a>
                                                        <a class="dropdown-item" href="{{ route('whatsapp-flows.use-in-automation', ['id' => $flow->id, 'recipe' => 'book_live']) }}">
                                                            <strong>Live slot booking</strong>
                                                            <div class="small text-muted">Form picks service + time → Reminders appointment</div>
                                                        </a>
                                                        <a class="dropdown-item" href="{{ route('whatsapp-flows.use-in-automation', ['id' => $flow->id, 'recipe' => 'book']) }}">Book from form answers</a>
                                                        <a class="dropdown-item" href="{{ route('whatsapp-flows.use-in-automation', ['id' => $flow->id, 'recipe' => 'event']) }}">Event registration</a>
                                                        <a class="dropdown-item" href="{{ route('whatsapp-flows.use-in-automation', ['id' => $flow->id, 'recipe' => 'checkout']) }}">Checkout &amp; pay</a>
                                                    </div>
                                                </div>
                                            @else
                                                <a
                                                    href="{{ route('whatsapp-flows.edit', $flow->id) }}"
                                                    title="Publish to WhatsApp first"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    style="margin-right: 3px;"
                                                >
                                                    <i class="ni ni-send"></i> Go Live first
                                                </a>
                                            @endif

                                            {{-- Preview --}}
                                            <button
                                                wire:click="openPreview({{ $flow->id }})"
                                                title="Preview"
                                                class="btn btn-info btn-sm"
                                                style="margin-right: 3px;"
                                            >
                                                <i class="ni ni-zoom-split-in"></i> Preview
                                            </button>

                                            {{-- Edit --}}
                                            <a
                                                href="{{ route('whatsapp-flows.edit', $flow->id) }}"
                                                title="Edit"
                                                class="btn btn-primary btn-sm"
                                                style="margin-right: 3px;"
                                            >
                                                <i class="ni ni-pencil-bold"></i> Edit
                                            </a>

                                            @if ($flow->meta_flow_id)
                                                <button
                                                    wire:click="refreshFlowSchema({{ $flow->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="refreshFlowSchema({{ $flow->id }})"
                                                    title="Refresh field schema from Meta"
                                                    class="btn btn-outline-info btn-sm"
                                                    style="margin-right: 3px;"
                                                >
                                                    <span wire:loading.remove wire:target="refreshFlowSchema({{ $flow->id }})">
                                                        <i class="ni ni-refresh-02"></i>
                                                    </span>
                                                    <span wire:loading wire:target="refreshFlowSchema({{ $flow->id }})">...</span>
                                                </button>
                                            @endif

                                            {{-- Delete --}}
                                            <button
                                                wire:click="confirmDelete({{ $flow->id }})"
                                                title="Delete"
                                                class="btn btn-danger btn-sm"
                                            >
                                                <i class="ni ni-trash-2"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($flows->hasPages())
                        <div class="card-body">
                            {{ $flows->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- ======================== DELETE CONFIRMATION MODAL ======================== --}}
    @if ($confirmDeleteId)
        <div class="modal d-block" style="background: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Flow</h5>
                        <button type="button" class="close" wire:click="cancelDelete">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="text-danger">
                            <i class="ni ni-fat-remove"></i>
                            This action cannot be undone.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelDelete">Cancel</button>
                        <button type="button" class="btn btn-danger btn-sm" wire:click="deleteFlow({{ $confirmDeleteId }})" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="deleteFlow">Delete</span>
                            <span wire:loading wire:target="deleteFlow">Deleting...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ======================== PREVIEW MODAL ======================== --}}
    @if ($previewFlow)
        <div class="modal d-block" style="background: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
                <div class="modal-content">

                    {{-- Modal Header --}}
                    <div class="modal-header py-3">
                        <div>
                            <h5 class="modal-title mb-0">{{ $previewFlow['name'] }}</h5>
                            <small class="text-muted">
                                Meta Flow Preview
                                @if ($metaPreviewExpiry)
                                    &nbsp;·&nbsp; expires {{ \Carbon\Carbon::parse($metaPreviewExpiry)->diffForHumans() }}
                                @endif
                            </small>
                        </div>
                        <button type="button" class="close" wire:click="closePreview">
                            <span>&times;</span>
                        </button>
                    </div>

                    {{-- Modal Body --}}
                    <div class="modal-body p-0" style="height: 760px; overflow: hidden;">

                        @if ($metaPreviewError)
                            {{-- Error / not published state --}}
                            <div class="d-flex flex-column align-items-center justify-content-center h-100 p-4 text-center">
                                <div class="text-warning mb-3" style="font-size: 3rem;">&#9888;</div>
                                <p class="text-muted mb-3">{{ $metaPreviewError }}</p>
                                @if (empty($previewFlow['meta_flow_id']))
                                    <a href="{{ route('whatsapp-flows.edit', $previewFlow['id']) }}"
                                       class="btn btn-primary btn-sm">
                                        Open in Builder to Publish
                                    </a>
                                @endif
                            </div>

                        @elseif ($metaPreviewUrl)
                            {{-- Meta iframe preview --}}
                            <iframe
                                src="{{ $metaPreviewUrl }}"
                                style="width: 100%; height: 100%; border: none; display: block;"
                                allow="clipboard-write"
                                title="WhatsApp Flow Preview"
                            ></iframe>

                        @else
                            {{-- Loading state --}}
                            <div class="d-flex flex-column align-items-center justify-content-center h-100">
                                <div class="spinner-border text-primary mb-3" role="status"></div>
                                <small class="text-muted">Loading preview from Meta...</small>
                            </div>
                        @endif

                    </div>

                    {{-- Modal Footer --}}
                    <div class="modal-footer py-2">
                        <small class="text-muted mr-auto">
                            Status: <strong>{{ ucfirst($previewFlow['status'] ?? 'draft') }}</strong>
                        </small>
                        @if ($metaPreviewUrl)
                            <a href="{{ $metaPreviewUrl }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                Open in New Tab
                            </a>
                        @endif
                        <a href="{{ route('whatsapp-flows.edit', $previewFlow['id']) }}"
                           class="btn btn-primary btn-sm">
                            Open in Builder
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Import from Meta Modal --}}
    @if ($showMetaImport)
        <div class="modal d-block" style="background: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Import from Meta</h5>
                            <small class="text-muted">Flows published in your WhatsApp Business Account</small>
                        </div>
                        <button type="button" class="close" wire:click="closeMetaImport">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <p class="text-muted small mb-0">
                                Import a flow to use it in automations, refresh its schema, and manage it alongside forms built in ConvoCon.
                            </p>
                            <button
                                type="button"
                                class="btn btn-outline-secondary btn-sm"
                                wire:click="loadMetaFlows"
                                wire:loading.attr="disabled"
                            >
                                <span wire:loading.remove wire:target="loadMetaFlows">
                                    <i class="ni ni-refresh-02"></i> Refresh list
                                </span>
                                <span wire:loading wire:target="loadMetaFlows">Loading...</span>
                            </button>
                        </div>

                        @if ($metaFlowsError)
                            <div class="alert alert-danger small mb-3">{{ $metaFlowsError }}</div>
                        @endif

                        @if ($metaFlowsLoading)
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary mb-3" role="status"></div>
                                <p class="text-muted small mb-0">Loading flows from Meta...</p>
                            </div>
                        @elseif (empty($metaFlows))
                            <div class="text-center py-5">
                                <i class="ni ni-cloud-download-95 text-muted" style="font-size: 42px;"></i>
                                <p class="text-muted mt-3 mb-0">No flows found in your Meta account.</p>
                                <p class="text-muted small">Create flows in Meta's Flow Builder or publish forms from here first.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Meta ID</th>
                                            <th>Status</th>
                                            <th>Local link</th>
                                            <th class="text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($metaFlows as $metaFlow)
                                            <tr wire:key="meta-flow-{{ $metaFlow['meta_flow_id'] }}">
                                                <td><strong>{{ $metaFlow['name'] }}</strong></td>
                                                <td><small class="text-muted">{{ $metaFlow['meta_flow_id'] }}</small></td>
                                                <td>
                                                    <span class="badge badge-{{ ($metaFlow['status'] ?? '') === 'PUBLISHED' ? 'success' : 'warning' }}">
                                                        {{ $metaFlow['status'] ?? 'UNKNOWN' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if (! empty($metaFlow['linked']))
                                                        <a href="{{ route('whatsapp-flows.edit', $metaFlow['local_flow_id']) }}" class="badge badge-info">
                                                            Linked (#{{ $metaFlow['local_flow_id'] }})
                                                        </a>
                                                    @else
                                                        <span class="text-muted small">Not imported</span>
                                                    @endif
                                                </td>
                                                <td class="text-right">
                                                    @if (! empty($metaFlow['linked']))
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-info btn-sm"
                                                            wire:click="importFromMeta('{{ $metaFlow['meta_flow_id'] }}')"
                                                            wire:loading.attr="disabled"
                                                            wire:target="importFromMeta('{{ $metaFlow['meta_flow_id'] }}')"
                                                        >
                                                            <span wire:loading.remove wire:target="importFromMeta('{{ $metaFlow['meta_flow_id'] }}')">Re-sync</span>
                                                            <span wire:loading wire:target="importFromMeta('{{ $metaFlow['meta_flow_id'] }}')">Syncing...</span>
                                                        </button>
                                                    @else
                                                        <button
                                                            type="button"
                                                            class="btn btn-primary btn-sm"
                                                            wire:click="importFromMeta('{{ $metaFlow['meta_flow_id'] }}')"
                                                            wire:loading.attr="disabled"
                                                            wire:target="importFromMeta('{{ $metaFlow['meta_flow_id'] }}')"
                                                        >
                                                            <span wire:loading.remove wire:target="importFromMeta('{{ $metaFlow['meta_flow_id'] }}')">Import</span>
                                                            <span wire:loading wire:target="importFromMeta('{{ $metaFlow['meta_flow_id'] }}')">Importing...</span>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="closeMetaImport">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Test Send Modal --}}
    @if ($testSendFlowId)
        <div class="modal d-block" style="background: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Send test form</h5>
                        <button type="button" class="close" wire:click="cancelTestSend"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Send this published form to a WhatsApp number for testing.</p>
                        <label class="small font-weight-600">Phone number (with country code)</label>
                        <input type="text" wire:model="testSendPhone" class="form-control" placeholder="e.g. 254712345678" />
                        @if ($testSendMessage)
                            <p class="small mt-2 mb-0">{{ $testSendMessage }}</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelTestSend">Cancel</button>
                        <button type="button" class="btn btn-success btn-sm" wire:click="sendTest" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="sendTest">Send test</span>
                            <span wire:loading wire:target="sendTest">Sending...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    document.addEventListener('livewire:init', function () {
        Livewire.on('showNotification', ({ type, message }) => {
            alert(message);
        });
    });
</script>
@endscript
