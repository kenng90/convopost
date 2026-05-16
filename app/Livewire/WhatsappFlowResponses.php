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

    // Loading states
    public bool $loadingResponse = false;
    public bool $closingResponse = false;

    protected $queryString = [
        'flowId' => ['except' => null],
        'statusFilter' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function updatingFlowId(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    /**
     * View Response with loading indicator
     */
    public function viewResponse(int $id): void
    {
        $this->loadingResponse = true;

        try {
            $response = WhatsappFlowResponse::with('whatsappFlow')->find($id);
            if (!$response) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Response not found']);
                $this->loadingResponse = false;
                return;
            }

            $data = $response->toArray();
            $data['flow_name'] = $response->whatsappFlow?->name ?? ('Flow #' . $response->whatsapp_flow_id);

            $rawResponses = $response->responses;
            if (is_string($rawResponses)) {
                $rawResponses = json_decode($rawResponses, true) ?? [];
            }
            $rawResponses = is_array($rawResponses) ? $rawResponses : [];

            $internalKeys = ['flow_token', 'version', 'action', 'screen', 'name'];
            $cleanResponses = array_filter(
                $rawResponses,
                fn ($key) => !in_array($key, $internalKeys, true),
                ARRAY_FILTER_USE_KEY
            );

            $data['responses_with_labels'] = $this->mapResponsesToLabels($cleanResponses, $response->whatsappFlow);
            $data['responses'] = $cleanResponses;
            $data['responses_missing'] = $response->status === 'completed' && empty($cleanResponses);

            $this->selectedResponse = $data;

        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Failed to load response: ' . $e->getMessage()]);
        } finally {
            $this->loadingResponse = false;
        }
    }

    public function closeViewResponse(): void
    {
        $this->selectedResponse = null;
        $this->loadingResponse = false;   // Reset in case
    }

    private function mapResponsesToLabels(array $responses, ?WhatsappFlow $flow): array
    {
        if (!$flow || empty($flow->flow_json['screens'] ?? [])) {
            return $this->fallbackResponseMapping($responses);
        }

        $fieldMap = [];
        foreach ($flow->flow_json['screens'] as $screen) {
            foreach ($screen['fields'] ?? [] as $field) {
                $key = $field['type'] . '_' . $field['id'];
                $fieldMap[$key] = [
                    'label' => $field['label'] ?? ucfirst(str_replace('_', ' ', $field['type'])),
                    'type'  => $field['type'],
                    'screen_title' => $screen['title'] ?? 'Screen',
                ];
            }
        }

        $enriched = [];
        foreach ($responses as $key => $value) {
            $fieldInfo = $fieldMap[$key] ?? null;
            $enriched[] = [
                'key'    => $key,
                'label'  => $fieldInfo['label'] ?? $this->humanizeKey($key),
                'value'  => $value,
                'type'   => $fieldInfo['type'] ?? null,
                'screen' => $fieldInfo['screen_title'] ?? null,
            ];
        }
        return $enriched;
    }

    private function fallbackResponseMapping(array $responses): array
    {
        return array_map(fn($key, $value) => [
            'key' => $key,
            'label' => $this->humanizeKey($key),
            'value' => $value,
            'type' => null,
            'screen' => null,
        ], array_keys($responses), $responses);
    }

    private function humanizeKey(string $key): string
    {
        $stripped = preg_replace('/^(text|textarea|radio|checkbox|select|date|chips|optin|media|dropdown)_\d+_?/i', '', $key);
        return $stripped
            ? ucwords(str_replace('_', ' ', $stripped))
            : ucwords(str_replace('_', ' ', $key));
    }


    public function render(): View
    {
        // ... your existing render logic (unchanged)
        $companyId = auth()->user()->company_id;

        $flows = WhatsappFlow::forSelect()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        $query = WhatsappFlowResponse::where('company_id', $companyId)
            ->with(['whatsappFlow' => fn ($q) => $q->forSelect()])
            ->when($this->flowId, fn ($q) => $q->where('whatsapp_flow_id', $this->flowId))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at');

        $responses = $query->paginate(20);

        $statsQuery = WhatsappFlowResponse::where('company_id', $companyId)
            ->when($this->flowId, fn ($q) => $q->where('whatsapp_flow_id', $this->flowId));

        $totalResponses = $statsQuery->count();
        $completedResponses = $statsQuery->where('status', 'completed')->count();
        $abandonedResponses = $statsQuery->where('status', 'abandoned')->count();
        $completionRate = $totalResponses > 0 ? round(($completedResponses / $totalResponses) * 100, 1) : 0;

        return view('livewire.whatsapp-flow-responses', compact(
            'flows', 'responses', 'totalResponses',
            'completedResponses', 'abandonedResponses', 'completionRate'
        ));
    }
}