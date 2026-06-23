<?php

namespace Modules\Flowmaker\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Flowmaker\AiFlowAssistantService;
use App\Services\Flowmaker\FlowTemplateService;
use App\Services\Platform\ManagedAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function install(Request $request, string $key): RedirectResponse
    {
        $this->ownerAndStaffOnly();
        $flow = $this->templates->install($key, $request->input('name'));

        if (! $flow) {
            return redirect()->route('flows.index')->withError(__('Template not found.'));
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
