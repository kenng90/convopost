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

        if (! $this->managedAi->canConsume($company, 5)) {
            return response()->json([
                'success' => false,
                'message' => __('Managed AI credits exhausted. Add an OpenRouter key or upgrade your plan.'),
            ], 402);
        }

        $draft = $this->assistant->generate($validated['description']);
        $flow = Flow::create(['name' => $validated['name'] ?: __('AI Draft Flow')]);
        $flow->flow_data = json_encode(['nodes' => $draft['nodes'], 'edges' => $draft['edges']]);
        $flow->save();

        $this->managedAi->consume($company, 5);
        $company->setConfig('activation_flow_installed', 'yes');

        return response()->json([
            'success' => true,
            'summary' => $draft['summary'],
            'flow_id' => $flow->id,
            'edit_url' => route('flowmaker.edit', $flow),
        ]);
    }
}
