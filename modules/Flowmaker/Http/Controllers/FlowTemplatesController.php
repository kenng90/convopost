<?php

namespace Modules\Flowmaker\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ListCatalog;
use App\Services\Flowmaker\AiFlowAssistantService;
use App\Services\Flowmaker\FlowTemplateService;
use App\Services\Platform\ManagedAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Contacts\Models\Group;
use Modules\Flowmaker\Models\Flow;

class FlowTemplatesController extends Controller
{
    public function __construct(
        private FlowTemplateService $templates,
        private AiFlowAssistantService $assistant,
        private ManagedAiService $managedAi,
    ) {
    }

    public function index(): RedirectResponse
    {
        $this->ownerAndStaffOnly();

        return redirect()->route('flows.index');
    }

    public function setup(string $key): JsonResponse
    {
        $this->ownerAndStaffOnly();
        $template = $this->templates->get($key);
        if (! $template) {
            return response()->json(['success' => false, 'message' => __('Template not found.')], 404);
        }

        $company = $this->getCompany();
        $catalogs = ListCatalog::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'catalog_mode']);

        $groups = Group::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $journeys = [];
        if (class_exists(\Modules\Journies\Models\Journey::class)) {
            $journeys = \Modules\Journies\Models\Journey::where('company_id', $company->id)
                ->with(['stages' => fn ($query) => $query->orderBy('order')->orderBy('id')->select('id', 'journey_id', 'name', 'order')])
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return response()->json([
            'success' => true,
            'template' => [
                'key' => $key,
                'name' => $template['name'] ?? $key,
                'category' => $template['category'] ?? 'general',
                'requires_setup_wizard' => (bool) ($template['requires_setup_wizard'] ?? false),
                'setup_hint' => $template['setup_hint'] ?? '',
            ],
            'catalogs' => $catalogs,
            'groups' => $groups,
            'journeys' => $journeys,
            'payment_providers' => [
                ['value' => 'auto', 'label' => 'Auto (company default)'],
                ['value' => 'mpesa', 'label' => 'M-Pesa STK'],
                ['value' => 'paystack', 'label' => 'Paystack'],
            ],
        ]);
    }

    public function install(Request $request, string $key): RedirectResponse|JsonResponse
    {
        $this->ownerAndStaffOnly();

        $bindings = [
            'catalog_id' => $request->input('catalog_id'),
            'payment_provider' => $request->input('payment_provider'),
            'group_id' => $request->input('group_id'),
            'journey_id' => $request->input('journey_id'),
            'stage_id' => $request->input('stage_id'),
            'keywords' => $request->input('keywords'),
        ];

        if (is_string($bindings['keywords'])) {
            $bindings['keywords'] = array_values(array_filter(array_map('trim', explode(',', $bindings['keywords']))));
        }

        $flow = $this->templates->install($key, $request->input('name'), array_filter(
            $bindings,
            fn ($value) => $value !== null && $value !== '' && $value !== []
        ));

        if (! $flow) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => __('Template not found.')], 404);
            }

            return redirect()->route('flows.index')->withError(__('Template not found.'));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'flow_id' => $flow->id,
                'edit_url' => route('flowmaker.edit', $flow),
            ]);
        }

        return redirect()->route('flowmaker.edit', $flow)
            ->withStatus(__('Template installed. Review and publish your flow.'));
    }

    public function generate(Request $request): JsonResponse
    {
        $this->ownerAndStaffOnly();
        $company = $this->getCompany();

        $validated = $request->validate([
            'description' => 'required|string|min:10|max:2000',
            'name' => 'nullable|string|max:120',
        ]);

        $cost = $this->managedAi->actionCost('ai_flow_generate');

        if (! $this->managedAi->canPerformAction($company, 'ai_flow_generate')) {
            return response()->json([
                'success' => false,
                'message' => $this->managedAi->exhaustionMessage($company),
            ], 402);
        }

        try {
            $draft = $this->assistant->generate($company, $validated['description']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $flow = Flow::create(['name' => $validated['name'] ?: __('AI Draft Flow')]);
        $flow->flow_data = json_encode(['nodes' => $draft['nodes'], 'edges' => $draft['edges']]);
        $flow->draft_flow_data = $flow->flow_data;
        $flow->save();

        $company->setConfig('activation_flow_installed', 'yes');

        return response()->json([
            'success' => true,
            'summary' => $draft['summary'],
            'source' => $draft['source'] ?? 'llm',
            'flow_id' => $flow->id,
            'edit_url' => route('flowmaker.edit', $flow),
            'credits_charged' => ($draft['source'] ?? '') === 'rules' && ! config('managed-ai.charge_rules_fallback', false)
                ? 0
                : ($this->managedAi->hasByokOpenRouter($company) ? 0 : $cost),
        ]);
    }
}
