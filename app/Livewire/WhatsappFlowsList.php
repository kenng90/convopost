<?php

namespace App\Livewire;

use App\Models\WhatsappFlow;
use App\Services\WhatsappMetaFlowService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class WhatsappFlowsList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public ?int $confirmDeleteId = null;
    public ?array $previewFlow = null;
    public ?string $previewScreenId = null;
    public ?string $metaPreviewUrl = null;
    public ?string $metaPreviewExpiry = null;
    public bool $metaPreviewLoading = false;
    public ?string $metaPreviewError = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    public function deleteFlow(int $id): void
    {
        $flow = WhatsappFlow::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();

        if (! $flow) {
            $this->dispatch('showNotification', type: 'error', message: 'Flow not found.');
            return;
        }

        $name       = $flow->name;
        $metaFlowId = $flow->meta_flow_id;

        // If flow was published to Meta, delete it there first
        if ($metaFlowId) {
            $service = new WhatsappMetaFlowService();
            $result  = $service->deleteFlowOnMeta($flow);

            if (! $result['success']) {
                // Still delete locally but warn the user about Meta
                $flow->delete();
                $this->confirmDeleteId = null;
                $this->dispatch('showNotification', type: 'error', message: "Flow \"{$name}\" deleted locally, but Meta deletion failed: " . $result['message']);
                return;
            }
        }

        $flow->delete();
        $this->confirmDeleteId = null;

        $message = $metaFlowId
            ? "Flow \"{$name}\" deleted locally and from Meta."
            : "Flow \"{$name}\" deleted.";

        $this->dispatch('showNotification', type: 'success', message: $message);
    }

    public function syncStatuses(): void
    {
        $user    = auth()->user();
        $company = \App\Models\Company::find($user->company_id);

        if (! $company) {
            $this->dispatch('showNotification', type: 'error', message: 'Company not found.');
            return;
        }

        $service = new WhatsappMetaFlowService();
        $result  = $service->syncAllStatuses($company);

        $type = $result['success'] ? 'success' : 'error';
        $this->dispatch('showNotification', type: $type, message: $result['message']);
    }

    public function openPreview(int $id): void
    {
        $flow = WhatsappFlow::where('id', $id)
            ->where('company_id', auth()->user()->company_id)
            ->first();

        if (! $flow) {
            return;
        }

        $this->previewFlow      = $flow->toArray();
        $this->metaPreviewUrl   = null;
        $this->metaPreviewExpiry = null;
        $this->metaPreviewError = null;

        // If the flow has been published to Meta, fetch its preview URL
        if (! empty($flow->meta_flow_id)) {
            $this->fetchMetaPreviewUrl($flow);
        } else {
            $this->metaPreviewError = 'This flow has not been published to Meta yet. Publish it first to use the Meta preview.';
            // Fall back to local screen preview
            $screens = $flow->flow_json['screens'] ?? [];
            $this->previewScreenId = ! empty($screens) ? ($screens[0]['id'] ?? null) : null;
        }
    }

    private function fetchMetaPreviewUrl(WhatsappFlow $flow): void
    {
        try {
            $company     = \App\Models\Company::find($flow->company_id);
            $accessToken = $company?->getConfig('whatsapp_permanent_access_token', '');

            if (empty($accessToken)) {
                $this->metaPreviewError = 'WhatsApp access token not configured.';
                return;
            }

            $apiVersion = 'v19.0';
            $url        = "https://graph.facebook.com/{$apiVersion}/{$flow->meta_flow_id}";

            $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->timeout(15)
                ->get($url, ['fields' => 'preview.invalidate(false)']);

            if ($response->successful()) {
                $data = $response->json();
                $this->metaPreviewUrl    = $data['preview']['preview_url'] ?? null;
                $this->metaPreviewExpiry = $data['preview']['expires_at'] ?? null;

                if (! $this->metaPreviewUrl) {
                    $this->metaPreviewError = 'Meta did not return a preview URL. The flow may need to be published first.';
                }
            } else {
                $error = $response->json()['error']['message'] ?? 'Unknown error';
                $this->metaPreviewError = 'Could not fetch preview from Meta: ' . $error;
            }
        } catch (\Exception $e) {
            $this->metaPreviewError = 'Error fetching Meta preview: ' . $e->getMessage();
        }
    }

    public function selectPreviewScreen(string $screenId): void
    {
        $this->previewScreenId = $screenId;
    }

    public function closePreview(): void
    {
        $this->previewFlow      = null;
        $this->previewScreenId  = null;
        $this->metaPreviewUrl   = null;
        $this->metaPreviewExpiry = null;
        $this->metaPreviewError = null;
    }

    public function render(): View
    {
        $companyId = auth()->user()->company_id;

        $query = WhatsappFlow::where('company_id', $companyId)
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('name', 'like', '%' . $this->search . '%')
                   ->orWhere('description', 'like', '%' . $this->search . '%');
            }))
            ->orderByDesc('updated_at');

        $flows = $query->paginate(15);

        // Build the preview screens for the currently previewed flow
        $previewScreens = [];
        $previewCurrentScreen = null;
        if ($this->previewFlow) {
            $previewScreens = $this->previewFlow['flow_json']['screens'] ?? [];
            foreach ($previewScreens as $screen) {
                if ($screen['id'] === $this->previewScreenId) {
                    $previewCurrentScreen = $screen;
                    break;
                }
            }
        }

        return view('livewire.whatsapp-flows-list', compact('flows', 'previewScreens', 'previewCurrentScreen'));
    }
}
