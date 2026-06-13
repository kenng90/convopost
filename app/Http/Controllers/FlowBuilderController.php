<?php

namespace App\Http\Controllers;

use App\Models\WhatsappFlow;
use App\Services\WhatsappMetaFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * REST API controller for the WhatsApp Flow Builder.
 *
 * All state lives in Alpine.js on the client.
 * These endpoints handle only explicit persistence actions:
 *   - Loading a flow's data on page open
 *   - Saving / auto-saving flow JSON
 *   - Publishing / re-publishing to Meta
 *
 * No endpoint is called on field click, screen switch, or any other
 * UI interaction — those are handled entirely client-side by Alpine.
 */
class FlowBuilderController extends Controller
{
    public function __construct(
        private WhatsappMetaFlowService $metaService
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LOAD
    // GET /api/flow-builder/{flow}
    // ─────────────────────────────────────────────────────────────────────────

    public function load(WhatsappFlow $flow): JsonResponse
    {
        \Log::info('FlowBuilderController@load called', [
            'flow_id' => $flow->id,
            'flow_name' => $flow->name ?? 'N/A',
            'company_id' => $flow->company_id,
        ]);

        $this->authorizeFlow($flow);

        $company = $flow->company;
        $endpointUrl = config('app.url').'/webhook/wpbox/flows/'
            .($company?->getConfig('plain_token', '') ?: 'unknown');

        \Log::info('Returning flow data as JSON', [
            'flow_id' => $flow->id,
            'screens_count' => count($flow->flow_json['screens'] ?? []),
            'meta_flow_id' => $flow->meta_flow_id,
        ]);

        return response()->json([
            'flow' => [
                'id' => $flow->id,
                'name' => $flow->name,
                'description' => $flow->description ?? '',
                'category' => $flow->category ?? 'OTHER',
                'status' => $flow->status,
                'meta_flow_id' => $flow->meta_flow_id,
                'meta_error' => $flow->meta_error,
                'published_at' => $flow->published_at?->toIso8601String(),
                'screens' => $flow->flow_json['screens'] ?? [],
            ],
            'endpoint_url' => $endpointUrl,
        ]);
    }
    // public function load(WhatsappFlow $flow): JsonResponse
    // {
    //     $this->authorizeFlow($flow);

    //     $company     = $flow->company;
    //     $endpointUrl = config('app.url') . '/webhook/wpbox/flows/'
    //         . ($company?->getConfig('plain_token', '') ?: 'unknown');

    //     return response()->json([
    //         'flow' => [
    //             'id'           => $flow->id,
    //             'name'         => $flow->name,
    //             'description'  => $flow->description ?? '',
    //             'category'     => $flow->category ?? 'OTHER',
    //             'status'       => $flow->status,
    //             'meta_flow_id' => $flow->meta_flow_id,
    //             'meta_error'   => $flow->meta_error,
    //             'published_at' => $flow->published_at?->toIso8601String(),
    //             'screens'      => $flow->flow_json['screens'] ?? [],
    //         ],
    //         'endpoint_url' => $endpointUrl,
    //     ]);
    //     return view('flows.builder', ['flow' => $flow]);
    //     // return view('whatsapp-flows.builder', compact('flow'));
    // }

    // ─────────────────────────────────────────────────────────────────────────
    // SAVE
    // POST /api/flow-builder (create)
    // PUT  /api/flow-builder/{flow} (update)
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'screens' => 'required|array',
        ]);

        $user = Auth::user();
        $company = $user->currentCompany();

        if (! $company) {
            return response()->json(['error' => 'No company found.'], 422);
        }

        $flow = WhatsappFlow::create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'category' => $data['category'] ?? 'OTHER',
            'flow_json' => ['screens' => $data['screens']],
            'status' => 'draft',
        ]);

        return response()->json([
            'success' => true,
            'flow_id' => $flow->id,
            'message' => 'Flow created.',
        ], 201);
    }

    public function update(Request $request, WhatsappFlow $flow): JsonResponse
    {
        $this->authorizeFlow($flow);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'screens' => 'required|array',
        ]);

        $flow->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? $flow->description,
            'category' => $data['category'] ?? $flow->category,
            'flow_json' => ['screens' => $data['screens']],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Flow saved.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLISH
    // POST /api/flow-builder/{flow}/publish
    // ─────────────────────────────────────────────────────────────────────────

    public function validateFlow(Request $request): JsonResponse
    {
        $data = $request->validate([
            'screens' => 'required|array',
        ]);

        $flow = new WhatsappFlow([
            'name' => $request->input('name', 'Validation'),
            'flow_json' => ['screens' => $data['screens']],
        ]);

        $result = $this->metaService->getPublishValidation($flow);

        return response()->json([
            'success' => $result['errors'] === [],
            'errors' => $result['errors'],
            'warnings' => $result['warnings'],
        ]);
    }

    public function publish(Request $request, WhatsappFlow $flow): JsonResponse
    {
        $this->authorizeFlow($flow);

        // Persist latest screens before publishing
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'screens' => 'required|array',
        ]);

        $flow->update([
            'name' => $data['name'],
            'flow_json' => ['screens' => $data['screens']],
        ]);

        $result = $this->metaService->publishFlow($flow);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Flow published to Meta successfully.',
                'meta_flow_id' => $result['meta_flow_id'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Publish failed.',
            'error' => $result['error'] ?? null,
        ], 422);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RE-PUBLISH
    // POST /api/flow-builder/{flow}/republish
    // ─────────────────────────────────────────────────────────────────────────

    public function republish(Request $request, WhatsappFlow $flow): JsonResponse
    {
        $this->authorizeFlow($flow);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'screens' => 'required|array',
        ]);

        $flow->update([
            'name' => $data['name'],
            'flow_json' => ['screens' => $data['screens']],
        ]);

        $credentials = $this->metaService->getCredentialsFromCompany($flow->company);

        if ($flow->meta_flow_id) {
            $push = $this->metaService->updateFlowOnMeta($flow, $credentials);
            if (! $push['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $push['message'],
                ], 422);
            }
        }

        $result = $this->metaService->republishFlowOnMeta($flow, $credentials);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? ($result['success'] ? 'Re-published.' : 'Re-publish failed.'),
        ], $result['success'] ? 200 : 422);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // WEBHOOK ENDPOINT URL
    // GET /api/flow-builder/endpoint-url
    // ─────────────────────────────────────────────────────────────────────────

    public function endpointUrl(): JsonResponse
    {
        $user = Auth::user();
        $company = $user->currentCompany();
        $token = $company?->getConfig('plain_token', '') ?: 'unknown';

        return response()->json([
            'url' => config('app.url').'/webhook/wpbox/flows/'.$token,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function authorizeFlow(WhatsappFlow $flow): void
    {
        $user = Auth::user();
        if (! $user->ownsCompany($flow->company_id)) {
            abort(403, 'Unauthorized.');
        }
    }
}
