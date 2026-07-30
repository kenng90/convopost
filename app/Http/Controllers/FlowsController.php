<?php

namespace App\Http\Controllers;

use App\Models\WhatsappFlow;
use App\Services\Flowmaker\WhatsappFormAutomationFactory;
use App\Services\WhatsappFlowSendService;
use App\Services\WhatsappFlowSubmissionService;
use App\Services\WhatsappFormTemplateService;
use App\Services\WhatsappMetaFlowService;
use App\Services\WhatsappMetaFlowSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FlowsController extends Controller
{
    /**
     * Show flows management or builder page
     */
    /**
     * Show WhatsApp Flows list or builder page
     */
    /**
     * Show WhatsApp Flows list or builder page
     */
    /**
     * Show WhatsApp Flows list or builder page
     */
    public function index(?int $id = null)
    {
        if ($id) {
            $companyId = $this->activeCompanyId();

            $flow = WhatsappFlow::where('id', $id)
                ->where('company_id', $companyId)
                ->first();

            if ($flow) {
                return view('livewire.flows-builder', ['flowId' => $flow->id]);
            }
        }

        if (request()->path() === 'whatsapp-flows/create') {
            return view('livewire.flows-builder', ['flowId' => null]);
        }

        return view('flows.index');
    }

    /**
     * Get all flows for the Flowmaker flow builder (to select in WhatsApp Flow node)
     * Returns flows with basic info for the dropdown
     */
    public function listForBuilder()
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->activeCompanyId();

            $flows = WhatsappFlow::where('company_id', $companyId)
                ->when(! request()->boolean('include_drafts'), fn ($q) => $q->publishedToMeta())
                ->when(request()->boolean('include_drafts'), fn ($q) => $q->where('status', '!=', 'archived'))
                ->orderByDesc('updated_at')
                ->get()
                ->map(function ($flow) {
                    $submissionService = app(WhatsappFlowSubmissionService::class);

                    return [
                        'id' => $flow->id,
                        'name' => $flow->name,
                        'status' => $flow->status,
                        'meta_flow_id' => $flow->meta_flow_id,
                        'flow_source' => $flow->flow_source ?? 'local',
                        'live' => filled($flow->meta_flow_id),
                        'lifecycle' => $flow->getLifecycleLabel(),
                        'screen_count' => count($flow->flow_json['screens'] ?? []),
                        'fields' => $submissionService->getFieldOptionsForForm($flow),
                        'default_cta' => $flow->default_cta,
                        'default_header' => $flow->default_header,
                        'default_footer' => $flow->default_footer,
                        'meta_synced_at' => optional($flow->meta_synced_at)->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'flows' => $flows,
            ]);

        } catch (\Exception $e) {
            Log::error('List flows for builder failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all flows for company
     */
    public function list(Request $request)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->getSelectedCompany($request);
            $status = $request->query('status', null); // Filter by status

            $query = WhatsappFlow::where('company_id', $companyId);

            if ($status && in_array($status, ['draft', 'published', 'archived'])) {
                $query->where('status', $status);
            }

            $flows = $query->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($flow) {
                    return [
                        'id' => $flow->id,
                        'name' => $flow->name,
                        'description' => $flow->description,
                        'status' => $flow->status,
                        'version' => $flow->version,
                        'field_count' => count($flow->getFields()),
                        'created_at' => $flow->created_at->format('Y-m-d H:i'),
                        'updated_at' => $flow->updated_at->format('Y-m-d H:i'),
                    ];
                });

            return response()->json([
                'success' => true,
                'flows' => $flows,
                'total' => $flows->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('List flows failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get flow details for editing
     */
    public function getFlow($id, Request $request)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->getSelectedCompany($request);
            $flow = WhatsappFlow::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'flow' => [
                    'id' => $flow->id,
                    'name' => $flow->name,
                    'description' => $flow->description,
                    'status' => $flow->status,
                    'version' => $flow->version,
                    'notes' => $flow->notes,
                    'flow_json' => $flow->flow_json,
                    'created_at' => $flow->created_at,
                    'updated_at' => $flow->updated_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get flow failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Flow not found',
            ], 404);
        }
    }

    /**
     * Create new flow
     */
    public function create(Request $request)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'flow_json' => 'required|array',
            ]);

            $companyId = $this->activeCompanyId();

            // Check for duplicate name in same company
            $exists = WhatsappFlow::where('company_id', $companyId)
                ->where('name', $validated['name'])
                ->where('status', '!=', 'archived')
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A flow with this name already exists',
                ], 400);
            }

            $flow = WhatsappFlow::create([
                'company_id' => $companyId,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'flow_json' => $validated['flow_json'],
                'status' => 'draft',
                'version' => 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Flow '{$validated['name']}' created successfully",
                'flow' => [
                    'id' => $flow->id,
                    'name' => $flow->name,
                    'status' => $flow->status,
                    'version' => $flow->version,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Create flow failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update flow
     */
    public function update(Request $request, $id)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->activeCompanyId();
            $flow = WhatsappFlow::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'flow_json' => 'required|array',
                'status' => 'nullable|string|in:draft,published,archived',
                'notes' => 'nullable|string|max:5000',
            ]);

            // Check for duplicate name (excluding current flow)
            $exists = WhatsappFlow::where('company_id', $companyId)
                ->where('name', $validated['name'])
                ->where('id', '!=', $id)
                ->where('status', '!=', 'archived')
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A flow with this name already exists',
                ], 400);
            }

            $flow->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'flow_json' => $validated['flow_json'],
                'status' => $validated['status'] ?? $flow->status,
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Flow updated successfully',
                'flow' => [
                    'id' => $flow->id,
                    'name' => $flow->name,
                    'status' => $flow->status,
                    'updated_at' => $flow->updated_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Update flow failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete flow
     */
    public function delete($id, Request $request)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->getSelectedCompany($request);
            $flow = WhatsappFlow::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $flowName = $flow->name;
            $flow->delete();

            return response()->json([
                'success' => true,
                'message' => "Flow '{$flowName}' deleted successfully",
            ]);

        } catch (\Exception $e) {
            Log::error('Delete flow failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Publish flow
     */
    public function publish($id, Request $request)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->getSelectedCompany($request);
            $flow = WhatsappFlow::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            // Validate flow has required fields
            $fields = $flow->getFields();
            if (empty($fields)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Flow must have at least one field before publishing',
                ], 400);
            }

            $flow->update(['status' => 'published']);

            return response()->json([
                'success' => true,
                'message' => 'Flow published successfully',
                'flow' => [
                    'id' => $flow->id,
                    'status' => $flow->status,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Publish flow failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Archive flow
     */
    public function archive($id, Request $request)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->getSelectedCompany($request);
            $flow = WhatsappFlow::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $flow->update(['status' => 'archived']);

            return response()->json([
                'success' => true,
                'message' => 'Flow archived successfully',
                'flow' => [
                    'id' => $flow->id,
                    'status' => $flow->status,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Archive flow failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Publish flow to Meta WhatsApp Flows API
     */
    public function publishToMeta(int $id, Request $request)
    {
        Log::info('publishToMeta controller action called', ['flow_id' => $id]);

        try {
            $companyId = $this->getSelectedCompany($request);

            Log::debug('Company ID determined', [
                'flow_id' => $id,
                'company_id' => $companyId,
            ]);

            $flow = WhatsappFlow::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            Log::info('Flow retrieved', [
                'flow_id' => $flow->id,
                'flow_name' => $flow->name,
                'company_id' => $flow->company_id,
            ]);

            $company = $flow->company;

            // Check if Meta credentials exist in config
            $accessToken = $company->getConfig('whatsapp_permanent_access_token');
            $businessAccountId = $company->getConfig('whatsapp_business_account_id');

            Log::debug('Credentials check', [
                'flow_id' => $id,
                'company_id' => $companyId,
                'has_access_token' => ! empty($accessToken),
                'has_business_account_id' => ! empty($businessAccountId),
                'business_account_id' => $businessAccountId,
            ]);

            if (! $accessToken || ! $businessAccountId) {
                Log::warning('Missing Meta credentials', [
                    'flow_id' => $id,
                    'company_id' => $companyId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Meta API credentials not configured for this organization. Please set up WhatsApp credentials first.',
                ], 400);
            }

            // Publish flow to Meta
            Log::info('Calling WhatsappMetaFlowService to publish flow', [
                'flow_id' => $flow->id,
                'flow_name' => $flow->name,
            ]);

            $service = new WhatsappMetaFlowService();
            $result = $service->publishFlow($flow);

            Log::debug('Service result received', [
                'flow_id' => $flow->id,
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? '',
            ]);

            if ($result['success']) {
                // Update flow with waba_id
                $flow->update([
                    'waba_id' => $businessAccountId,
                ]);

                Log::info('Flow successfully published to Meta and updated in database', [
                    'flow_id' => $flow->id,
                    'meta_flow_id' => $result['meta_flow_id'],
                    'waba_id' => $businessAccountId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'flow' => [
                        'id' => $flow->id,
                        'meta_flow_id' => $result['meta_flow_id'],
                        'status' => 'published',
                        'published_at' => $flow->published_at,
                    ],
                ]);
            }

            Log::error('Flow publish failed from service', [
                'flow_id' => $flow->id,
                'message' => $result['message'] ?? '',
                'error' => $result['error'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error' => $result['error'] ?? null,
            ], 400);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Flow not found when publishing to Meta', [
                'flow_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Flow not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Publish flow to Meta controller exception', [
                'flow_id' => $id,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to publish flow: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get selected company for multi-tenant support
     */
    private function getSelectedCompany(Request $request)
    {
        $user = auth()->user();
        $selectedCompanyId = $request->query('company_id', null);

        // If company_id is provided, verify user has access
        if ($selectedCompanyId) {
            $accessibleCompanies = $user->accessibleCompanies();
            $hasAccess = $accessibleCompanies->contains('id', $selectedCompanyId);

            if (! $hasAccess) {
                throw new \Exception('Unauthorized to access this company');
            }

            return $selectedCompanyId;
        }

        return $user->activeCompanyId();
    }

    /**
     * Get form field definitions for conditional routing in Flowmaker.
     */
    public function getFields(int $id): \Illuminate\Http\JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $companyId = $this->activeCompanyId();
        $flow = WhatsappFlow::where('id', $id)->where('company_id', $companyId)->firstOrFail();
        $fields = app(WhatsappFlowSubmissionService::class)->getFieldOptionsForForm($flow);

        return response()->json(['success' => true, 'fields' => $fields]);
    }

    /**
     * Meta Live readiness checklist for a form.
     */
    public function readiness(int $id): \Illuminate\Http\JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $companyId = $this->activeCompanyId();
        $flow = WhatsappFlow::where('id', $id)->where('company_id', $companyId)->firstOrFail();
        $summary = app(\App\Services\WhatsappFlowReadinessService::class)->forForm($flow);

        return response()->json(array_merge(['success' => true], $summary));
    }

    /**
     * Company-level WhatsApp Forms health for Flowmaker Sell panel.
     */
    public function formsHealth(): \Illuminate\Http\JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $companyId = $this->activeCompanyId();
        $company = \App\Models\Company::find($companyId);
        $readiness = app(\App\Services\WhatsappFlowReadinessService::class)->forCompany($company);

        $total = WhatsappFlow::where('company_id', $companyId)->where('status', '!=', 'archived')->count();
        $live = WhatsappFlow::where('company_id', $companyId)->publishedToMeta()->count();
        $abandoned = \App\Models\WhatsappFlowResponse::query()
            ->where('company_id', $companyId)
            ->where('status', 'abandoned')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $completed = \App\Models\WhatsappFlowResponse::query()
            ->where('company_id', $companyId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return response()->json([
            'success' => true,
            'ready' => $readiness['ready'] ?? false,
            'steps' => $readiness['steps'] ?? [],
            'totals' => [
                'forms' => $total,
                'live' => $live,
                'completed_30d' => $completed,
                'abandoned_30d' => $abandoned,
            ],
            'links' => [
                'forms' => route('whatsapp-flows.index'),
                'responses' => route('whatsapp-flows.responses'),
                'keys' => route('admin.apps.company').'#facebook_developer',
            ],
        ]);
    }

    /**
     * Form → conversion analytics for a WhatsApp Form.
     */
    public function conversion(int $id): \Illuminate\Http\JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $companyId = $this->activeCompanyId();
        $flow = WhatsappFlow::where('id', $id)->where('company_id', $companyId)->firstOrFail();
        $summary = app(\App\Services\Flowmaker\FormConversionAnalyticsService::class)->summaryForForm($flow->id);

        return response()->json(array_merge(['success' => true], $summary));
    }

    /**
     * Send a test form to a phone number.
     */
    public function testSend(int $id, Request $request): \Illuminate\Http\JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $companyId = $this->activeCompanyId();
        $flow = WhatsappFlow::where('id', $id)->where('company_id', $companyId)->firstOrFail();

        $result = app(WhatsappFlowSendService::class)->sendTest(
            $flow,
            $validated['phone'],
            $companyId
        );

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * List available form templates for the builder gallery.
     */
    public function listTemplates(): \Illuminate\Http\JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'templates' => app(WhatsappFormTemplateService::class)->listForGallery(),
        ]);
    }

    /**
     * Create a draft form from a template bundle.
     */
    public function createFromBundle(string $key, Request $request): \Illuminate\Http\JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $companyId = $this->activeCompanyId();
            $flow = app(WhatsappFormTemplateService::class)->createFromTemplate(
                $key,
                $companyId,
                $request->input('name')
            );

            return response()->json([
                'success' => true,
                'flow' => [
                    'id' => $flow->id,
                    'name' => $flow->name,
                ],
                'redirect' => route('whatsapp-flows.edit', $flow->id),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * Deep-link helper: create a Flowmaker automation from this Live form.
     */
    public function useInAutomation(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $companyId = $this->activeCompanyId();
        $form = WhatsappFlow::where('id', $id)->where('company_id', $companyId)->firstOrFail();

        if (empty($form->meta_flow_id)) {
            return redirect()
                ->route('whatsapp-flows.edit', $form->id)
                ->with('error', __('Publish this form to WhatsApp (Go Live) before using it in automation.'));
        }

        $recipe = strtolower((string) $request->query('recipe', 'collect'));
        if (! in_array($recipe, WhatsappFormAutomationFactory::RECIPES, true)) {
            $recipe = 'collect';
        }

        try {
            $flow = app(WhatsappFormAutomationFactory::class)->createFromForm($form, $recipe, (int) $companyId);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('whatsapp-flows.edit', $form->id)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('flowmaker.edit', $flow)
            ->with('success', __('Automation draft created. Review nodes, bind team/payment settings, then publish.'));
    }

    /**
     * List flows available on Meta for import/linking.
     */
    public function listMetaFlows(): \Illuminate\Http\JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $company = \App\Models\Company::find($this->activeCompanyId());
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $result = app(WhatsappMetaFlowSyncService::class)->listMetaFlows($company);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Import or refresh a flow from Meta by meta_flow_id.
     */
    public function importFromMeta(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'meta_flow_id' => 'required|string|max:64',
            'name' => 'nullable|string|max:255',
        ]);

        $company = \App\Models\Company::find($this->activeCompanyId());
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $result = app(WhatsappMetaFlowSyncService::class)->importOrRefreshFromMeta(
            $company,
            $validated['meta_flow_id'],
            $validated['name'] ?? null,
        );

        if (! ($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        /** @var WhatsappFlow $flow */
        $flow = $result['flow'];

        return response()->json([
            'success' => true,
            'flow' => [
                'id' => $flow->id,
                'name' => $flow->name,
                'meta_flow_id' => $flow->meta_flow_id,
                'flow_source' => $flow->flow_source ?? 'meta_linked',
                'fields' => $result['fields'] ?? app(WhatsappFlowSubmissionService::class)->getFieldOptionsForForm($flow),
            ],
            'message' => $result['message'] ?? 'Imported from Meta.',
        ]);
    }

    /**
     * Refresh local schema from Meta for an existing flow.
     */
    public function refreshSchema(int $id): \Illuminate\Http\JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $companyId = $this->activeCompanyId();
        $flow = WhatsappFlow::where('id', $id)->where('company_id', $companyId)->firstOrFail();
        $result = app(WhatsappMetaFlowSyncService::class)->refreshSchema($flow);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Link a Meta flow ID for direct use in automations.
     */
    public function linkMetaFlow(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'meta_flow_id' => 'required|string|max:64',
        ]);

        $company = \App\Models\Company::find($this->activeCompanyId());
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 404);
        }

        $result = app(WhatsappMetaFlowSyncService::class)->linkMetaFlow($company, $validated['meta_flow_id']);
        if (! ($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        /** @var WhatsappFlow $flow */
        $flow = $result['flow'];

        return response()->json([
            'success' => true,
            'flow' => [
                'id' => $flow->id,
                'name' => $flow->name,
                'meta_flow_id' => $flow->meta_flow_id,
                'fields' => $result['fields'] ?? [],
            ],
        ]);
    }
}
