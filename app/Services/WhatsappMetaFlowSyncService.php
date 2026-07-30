<?php

namespace App\Services;

use App\Models\Company;
use App\Models\WhatsappFlow;
use App\Support\WhatsappGraphApi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappMetaFlowSyncService
{
    public function __construct(
        private WhatsappMetaFlowService $metaFlowService,
        private WhatsappMetaFlowJsonImporter $jsonImporter,
        private WhatsappFlowSubmissionService $submissionService,
    ) {
    }

    /**
     * @return array{success: bool, flows?: list<array<string, mixed>>, message?: string}
     */
    public function listMetaFlows(Company $company): array
    {
        $credentials = $this->metaFlowService->getCredentialsFromCompany($company);
        if (! $credentials) {
            return ['success' => false, 'message' => 'Meta API credentials are not configured.'];
        }

        $wabaId = $credentials['business_account_id'] ?? $credentials['waba_id'] ?? null;
        if (! $wabaId) {
            return ['success' => false, 'message' => 'WhatsApp Business Account ID is missing.'];
        }

        try {
            $response = Http::withToken($credentials['access_token'])
                ->timeout(30)
                ->get(WhatsappGraphApi::url("{$wabaId}/flows"), [
                    'fields' => 'id,name,status,categories,validation_errors',
                ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => $response->json()['error']['message'] ?? 'Failed to list flows from Meta.',
                ];
            }

            $items = $response->json('data') ?? [];
            $localByMetaId = WhatsappFlow::query()
                ->where('company_id', $company->id)
                ->whereNotNull('meta_flow_id')
                ->pluck('id', 'meta_flow_id');

            $flows = array_map(function (array $item) use ($localByMetaId) {
                $metaId = (string) ($item['id'] ?? '');

                return [
                    'meta_flow_id' => $metaId,
                    'name' => $item['name'] ?? 'Untitled',
                    'status' => strtoupper((string) ($item['status'] ?? 'DRAFT')),
                    'categories' => $item['categories'] ?? [],
                    'validation_errors' => $item['validation_errors'] ?? [],
                    'local_flow_id' => $localByMetaId[$metaId] ?? null,
                    'linked' => isset($localByMetaId[$metaId]),
                ];
            }, $items);

            return ['success' => true, 'flows' => $flows];
        } catch (\Throwable $e) {
            Log::error('listMetaFlows failed', ['company_id' => $company->id, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Import or refresh a flow from Meta by meta_flow_id.
     *
     * @return array{success: bool, flow?: WhatsappFlow, message?: string}
     */
    public function importOrRefreshFromMeta(Company $company, string $metaFlowId, ?string $name = null): array
    {
        $credentials = $this->metaFlowService->getCredentialsFromCompany($company);
        if (! $credentials) {
            return ['success' => false, 'message' => 'Meta API credentials are not configured.'];
        }

        $metaJsonResult = $this->fetchFlowJsonFromMeta($metaFlowId, $credentials['access_token']);
        if (! ($metaJsonResult['success'] ?? false)) {
            return ['success' => false, 'message' => $metaJsonResult['message'] ?? 'Could not fetch flow JSON from Meta.'];
        }

        $metaFlowJson = $metaJsonResult['flow_json'];
        $localFlowJson = $this->jsonImporter->toLocalFormat($metaFlowJson);
        $flowName = $name ?: ($metaJsonResult['name'] ?? 'Imported Meta Flow');

        $flow = WhatsappFlow::query()
            ->where('company_id', $company->id)
            ->where('meta_flow_id', $metaFlowId)
            ->first();

        if ($flow) {
            $flow->update([
                'name' => $flowName,
                'flow_json' => $localFlowJson,
                'meta_flow_json' => $metaFlowJson,
                'flow_source' => 'meta_linked',
                'meta_synced_at' => now(),
                'status' => $this->mapMetaStatus($metaJsonResult['status'] ?? 'DRAFT'),
            ]);
        } else {
            $flow = WhatsappFlow::create([
                'company_id' => $company->id,
                'name' => $flowName,
                'flow_json' => $localFlowJson,
                'meta_flow_json' => $metaFlowJson,
                'meta_flow_id' => $metaFlowId,
                'flow_source' => 'meta_linked',
                'meta_synced_at' => now(),
                'status' => $this->mapMetaStatus($metaJsonResult['status'] ?? 'DRAFT'),
                'waba_id' => $credentials['waba_id'] ?? null,
            ]);
        }

        return [
            'success' => true,
            'flow' => $flow->fresh(),
            'message' => 'Flow synced from Meta.',
            'fields' => $this->submissionService->getFieldOptionsForForm($flow),
        ];
    }

    /**
     * Link a Meta-only flow for use in automations (creates local stub if needed).
     */
    public function linkMetaFlow(Company $company, string $metaFlowId): array
    {
        $existing = WhatsappFlow::query()
            ->where('company_id', $company->id)
            ->where('meta_flow_id', $metaFlowId)
            ->first();

        if ($existing) {
            return ['success' => true, 'flow' => $existing, 'fields' => $this->submissionService->getFieldOptionsForForm($existing)];
        }

        return $this->importOrRefreshFromMeta($company, $metaFlowId);
    }

    /**
     * Refresh schema for an existing local flow from Meta.
     *
     * @return array{success: bool, fields?: list<array<string, mixed>>, message?: string}
     */
    public function refreshSchema(WhatsappFlow $flow): array
    {
        if (empty($flow->meta_flow_id) || ! $flow->company) {
            return ['success' => false, 'message' => 'Flow is not linked to Meta.'];
        }

        $result = $this->importOrRefreshFromMeta($flow->company, $flow->meta_flow_id, $flow->name);
        if (! ($result['success'] ?? false)) {
            return $result;
        }

        return [
            'success' => true,
            'fields' => $result['fields'] ?? [],
            'message' => 'Schema refreshed from Meta.',
        ];
    }

    /**
     * @return array{success: bool, flow_json?: array<string, mixed>, name?: string, status?: string, message?: string}
     */
    public function fetchFlowJsonFromMeta(string $metaFlowId, string $accessToken): array
    {
        try {
            $metaResponse = Http::withToken($accessToken)
                ->timeout(30)
                ->get(WhatsappGraphApi::url($metaFlowId), [
                    'fields' => 'id,name,status',
                ]);

            if (! $metaResponse->successful()) {
                return [
                    'success' => false,
                    'message' => $metaResponse->json()['error']['message'] ?? 'Meta flow lookup failed.',
                ];
            }

            $metaData = $metaResponse->json();

            $assetsResponse = Http::withToken($accessToken)
                ->timeout(30)
                ->get(WhatsappGraphApi::url("{$metaFlowId}/assets"));

            if (! $assetsResponse->successful()) {
                return [
                    'success' => false,
                    'message' => $assetsResponse->json()['error']['message'] ?? 'Could not fetch flow assets from Meta.',
                ];
            }

            $assets = $assetsResponse->json('data') ?? [];
            $downloadUrl = null;

            foreach ($assets as $asset) {
                if (($asset['asset_type'] ?? '') === 'FLOW_JSON') {
                    $downloadUrl = $asset['download_url'] ?? null;
                    break;
                }
            }

            if (! $downloadUrl) {
                return ['success' => false, 'message' => 'Meta flow JSON asset not found.'];
            }

            $jsonResponse = Http::timeout(60)->get($downloadUrl);
            if (! $jsonResponse->successful()) {
                return ['success' => false, 'message' => 'Failed to download flow JSON from Meta.'];
            }

            $flowJson = $jsonResponse->json();
            if (! is_array($flowJson)) {
                return ['success' => false, 'message' => 'Downloaded Meta flow JSON is invalid.'];
            }

            return [
                'success' => true,
                'flow_json' => $flowJson,
                'name' => $metaData['name'] ?? 'Meta Flow',
                'status' => strtoupper((string) ($metaData['status'] ?? 'DRAFT')),
            ];
        } catch (\Throwable $e) {
            Log::error('fetchFlowJsonFromMeta failed', ['meta_flow_id' => $metaFlowId, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function mapMetaStatus(string $metaStatus): string
    {
        return match (strtolower($metaStatus)) {
            'published' => 'published',
            'deprecated' => 'archived',
            default => 'draft',
        };
    }
}
