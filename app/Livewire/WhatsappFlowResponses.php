<?php

namespace App\Livewire;

use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowResponse;
use App\Services\WhatsappFlowResponseService;
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

    public string $activeTab = 'submissions';

    public bool $showTechnicalFields = false;

    public ?string $analyticsFieldFilter = null;

    public ?string $analyticsValueFilter = null;

    public ?array $selectedResponse = null;

    public bool $loadingResponse = false;

    protected $queryString = [
        'flowId' => ['except' => null],
        'statusFilter' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'activeTab' => ['except' => 'submissions'],
    ];

    public function updatingFlowId(): void
    {
        $this->resetPage();
        $this->clearAnalyticsFilter();
        $this->activeTab = 'submissions';
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function clearAnalyticsFilter(): void
    {
        $this->analyticsFieldFilter = null;
        $this->analyticsValueFilter = null;
        $this->resetPage();
    }

    public function filterByChoice(string $fieldKey, string $valueLabel): void
    {
        $this->analyticsFieldFilter = $fieldKey;
        $this->analyticsValueFilter = $valueLabel;
        $this->activeTab = 'submissions';
        $this->resetPage();
    }

    public function viewResponse(int $id): void
    {
        $this->loadingResponse = true;

        try {
            $service = app(WhatsappFlowResponseService::class);
            $response = WhatsappFlowResponse::with('whatsappFlow')->find($id);

            if (! $response) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Response not found']);
                $this->loadingResponse = false;

                return;
            }

            $data = $response->toArray();
            $data['flow_name'] = $response->whatsappFlow?->name ?? ('Flow #'.$response->whatsapp_flow_id);
            $cleanResponses = $service->cleanResponses($response->responses);

            $data['responses_with_labels'] = $service->enrichResponsesFlat($cleanResponses, $response->whatsappFlow);
            $data['responses_by_screen'] = $service->groupResponsesByScreen($cleanResponses, $response->whatsappFlow);
            $data['responses'] = $cleanResponses;
            $data['responses_missing'] = $response->status === 'completed' && empty($cleanResponses);
            $durationSeconds = ($response->sent_at && $response->completed_at)
                ? $response->sent_at->diffInSeconds($response->completed_at)
                : null;
            $data['duration_label'] = $service->formatDuration($durationSeconds);

            $this->selectedResponse = $data;
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Failed to load response: '.$e->getMessage()]);
        } finally {
            $this->loadingResponse = false;
        }
    }

    public function closeViewResponse(): void
    {
        $this->selectedResponse = null;
        $this->loadingResponse = false;
    }

    public function getExportUrl(): ?string
    {
        if (! $this->flowId) {
            return null;
        }

        return route('whatsapp-flows.responses.export', array_filter([
            'flowId' => $this->flowId,
            'statusFilter' => $this->statusFilter ?: null,
            'dateFrom' => $this->dateFrom ?: null,
            'dateTo' => $this->dateTo ?: null,
            'analyticsFieldFilter' => $this->analyticsFieldFilter,
            'analyticsValueFilter' => $this->analyticsValueFilter,
        ]));
    }

    public function render(): View
    {
        $service = app(WhatsappFlowResponseService::class);
        $companyId = auth()->user()->activeCompanyId();

        $flows = WhatsappFlow::forSelect()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        $selectedFlow = $this->flowId
            ? WhatsappFlow::where('company_id', $companyId)->find($this->flowId)
            : null;

        $query = $this->buildFilteredQuery($companyId, $selectedFlow, $service);

        $responses = $query->paginate(20);

        $statsQuery = WhatsappFlowResponse::where('company_id', $companyId)
            ->when($this->flowId, fn ($q) => $q->where('whatsapp_flow_id', $this->flowId))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo));

        $totalResponses = (clone $statsQuery)->count();
        $completedResponses = (clone $statsQuery)->where('status', 'completed')->count();
        $abandonedResponses = (clone $statsQuery)->where('status', 'abandoned')->count();
        $completionRate = $totalResponses > 0 ? round(($completedResponses / $totalResponses) * 100, 1) : 0;

        $fieldColumns = $selectedFlow ? $service->getInputFieldDefinitions($selectedFlow) : [];
        $tableRows = ($selectedFlow && $responses->isNotEmpty())
            ? $service->buildTableRows($responses->getCollection(), $selectedFlow)
            : [];

        $genericRows = (! $selectedFlow && $responses->isNotEmpty())
            ? $responses->getCollection()->map(function (WhatsappFlowResponse $response) use ($service) {
                $clean = $service->cleanResponses($response->responses);

                return [
                    'id' => $response->id,
                    'contact_name' => $response->contact_name ?? 'Unknown',
                    'contact_phone' => $response->contact_phone,
                    'flow_name' => $response->whatsappFlow?->name,
                    'preview' => $service->buildPreviewText($clean, $response->whatsappFlow),
                    'status' => $response->status,
                    'sent_at' => $response->sent_at,
                    'completed_at' => $response->completed_at,
                ];
            })->all()
            : [];

        $analyticsResponses = $selectedFlow
            ? $this->buildFilteredQuery($companyId, $selectedFlow, $service)->get()
            : collect();

        $choiceAnalytics = $selectedFlow
            ? $service->computeChoiceAnalytics($analyticsResponses, $selectedFlow)
            : [];

        $funnelAnalytics = $selectedFlow
            ? $service->computeFunnelAnalytics($analyticsResponses, $selectedFlow)
            : [];

        $avgCompletionSeconds = $service->averageCompletionSeconds($analyticsResponses);
        $avgCompletionLabel = $service->formatDuration($avgCompletionSeconds);
        $exportUrl = $this->getExportUrl();

        return view('livewire.whatsapp-flow-responses', compact(
            'flows',
            'responses',
            'totalResponses',
            'completedResponses',
            'abandonedResponses',
            'completionRate',
            'selectedFlow',
            'fieldColumns',
            'tableRows',
            'choiceAnalytics',
            'funnelAnalytics',
            'avgCompletionLabel',
            'exportUrl',
            'genericRows'
        ));
    }

    private function buildFilteredQuery(int $companyId, ?WhatsappFlow $selectedFlow, WhatsappFlowResponseService $service)
    {
        $query = WhatsappFlowResponse::where('company_id', $companyId)
            ->with(['whatsappFlow' => fn ($q) => $q->forSelect()])
            ->when($this->flowId, fn ($q) => $q->where('whatsapp_flow_id', $this->flowId))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderByDesc('created_at');

        if ($selectedFlow && $this->analyticsFieldFilter && $this->analyticsValueFilter) {
            $query = $service->applyFieldValueFilter(
                $query,
                $this->analyticsFieldFilter,
                $this->analyticsValueFilter,
                $selectedFlow
            );
        }

        return $query;
    }
}
