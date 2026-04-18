<?php

namespace App\Livewire;

use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowResponse;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class WhatsappFlowResponses extends Component
{
    use WithPagination;

    public ?int $flowId = null;
    public string $statusFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?array $selectedResponse = null;

    protected $queryString = [
        'flowId' => ['except' => null],
        'statusFilter' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function updatingFlowId(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function viewResponse(int $id): void
    {
        $response = WhatsappFlowResponse::with('whatsappFlow')->find($id);
        if (! $response) {
            return;
        }

        $data = $response->toArray();

        // Attach flow name for display
        $data['flow_name'] = $response->whatsappFlow?->name ?? ('Flow #' . $response->whatsapp_flow_id);

        // Normalise responses — the DB cast returns array, but guard against string (double-encoded)
        $rawResponses = $response->responses;
        if (is_string($rawResponses)) {
            $rawResponses = json_decode($rawResponses, true) ?? [];
        }
        $rawResponses = is_array($rawResponses) ? $rawResponses : [];

        // Strip internal/tracking keys
        $internalKeys = ['flow_token', 'version', 'action', 'screen', 'name'];
        $data['responses'] = array_filter(
            $rawResponses,
            fn ($key) => ! in_array($key, $internalKeys, true),
            ARRAY_FILTER_USE_KEY
        );

        // Flag whether the response was completed before the handler was in place
        $data['responses_missing'] = $response->status === 'completed' && empty($data['responses']);

        $this->selectedResponse = $data;
    }

    public function closeViewResponse(): void
    {
        $this->selectedResponse = null;
    }

    public function render(): View
    {
        $companyId = auth()->user()->company_id;

        // Get available flows for filter dropdown
        $flows = WhatsappFlow::where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        // Build query
        $query = WhatsappFlowResponse::where('company_id', $companyId)
            ->when($this->flowId, fn ($q) => $q->where('whatsapp_flow_id', $this->flowId))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at');

        $responses = $query->paginate(20);

        // Get stats for selected flow or all flows
        $statsQuery = WhatsappFlowResponse::where('company_id', $companyId)
            ->when($this->flowId, fn ($q) => $q->where('whatsapp_flow_id', $this->flowId));

        $totalResponses = $statsQuery->count();
        $completedResponses = $statsQuery->where('status', 'completed')->count();
        $abandonedResponses = $statsQuery->where('status', 'abandoned')->count();
        $completionRate = $totalResponses > 0 ? round(($completedResponses / $totalResponses) * 100, 1) : 0;

        return view('livewire.whatsapp-flow-responses', compact(
            'flows',
            'responses',
            'totalResponses',
            'completedResponses',
            'abandonedResponses',
            'completionRate'
        ));
    }
}
