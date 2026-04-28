<div class="container-fluid pt-5" wire:key="whatsapp-flows-list">

    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2 d-flex align-items-center">
                <i class="ni ni-chat-left-3 text-primary mr-2"></i>
                WhatsApp Flows
            </h1>
            <p class="text-muted small">Create and manage interactive WhatsApp flows</p>
        </div>
        <div class="col-md-4 text-right d-flex justify-content-end align-items-center" style="gap: 0.5rem;">
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
                                    <th>Flow</th>
                                    <th>Status</th>
                                    <th>Screens</th>
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
                                            @php
                                                $statusBadge = match($flow->status) {
                                                    'published' => 'badge-success',
                                                    'draft'     => 'badge-warning',
                                                    'archived'  => 'badge-secondary',
                                                    default     => 'badge-secondary',
                                                };
                                            @endphp
                                            <span class="badge {{ $statusBadge }}">{{ ucfirst($flow->status) }}</span>
                                            @if ($flow->meta_flow_id)
                                                <span class="badge badge-info ml-1">Meta ✓</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ count($flow->flow_json['screens'] ?? []) }}
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
