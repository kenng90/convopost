<?php

namespace App\Http\Controllers;

use App\Services\Outcomes\StoreCommerceWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreCommerceWebhookController extends Controller
{
    public function __construct(
        private readonly StoreCommerceWebhookService $commerce,
    ) {
    }

    public function shopify(Request $request, string $token): JsonResponse
    {
        $company = $this->commerce->resolveCompanyByToken($token);
        if (! $company) {
            return response()->json(['success' => false], 401);
        }

        $topic = (string) $request->header('X-Shopify-Topic', 'unknown');
        $externalId = (string) ($request->input('id') ?? $request->input('token') ?? '');
        $dedupeKey = 'shopify:'.$company->id.':'.$topic.':'.$externalId;

        if ($externalId !== '' && $this->alreadyProcessed($dedupeKey)) {
            return response()->json(['success' => true, 'duplicate' => true]);
        }

        $result = $this->commerce->handleShopify($company, $request);

        if ($externalId !== '') {
            $this->remember($company->id, 'shopify', $result['event'] ?? $topic, $externalId, $dedupeKey, [
                'triggered' => $result['triggered'] ?? 0,
            ]);
        }

        Log::info('Shopify commerce webhook handled', [
            'company_id' => $company->id,
            'topic' => $topic,
            'result' => $result,
        ]);

        return response()->json(['success' => true, 'result' => $result]);
    }

    public function woocommerce(Request $request, string $token): JsonResponse
    {
        $company = $this->commerce->resolveCompanyByToken($token);
        if (! $company) {
            return response()->json(['success' => false], 401);
        }

        $topic = (string) ($request->header('X-WC-Webhook-Topic') ?: $request->input('topic', 'unknown'));
        $externalId = (string) ($request->input('id') ?? '');
        $dedupeKey = 'woocommerce:'.$company->id.':'.$topic.':'.$externalId;

        if ($externalId !== '' && $this->alreadyProcessed($dedupeKey)) {
            return response()->json(['success' => true, 'duplicate' => true]);
        }

        $result = $this->commerce->handleWooCommerce($company, $request);

        if ($externalId !== '') {
            $this->remember($company->id, 'woocommerce', $result['event'] ?? $topic, $externalId, $dedupeKey, [
                'triggered' => $result['triggered'] ?? 0,
            ]);
        }

        return response()->json(['success' => true, 'result' => $result]);
    }

    private function alreadyProcessed(string $dedupeKey): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('store_commerce_webhook_events')) {
            return false;
        }

        return DB::table('store_commerce_webhook_events')->where('dedupe_key', $dedupeKey)->exists();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function remember(int $companyId, string $provider, string $eventType, string $externalId, string $dedupeKey, array $meta): void
    {
        if (! DB::getSchemaBuilder()->hasTable('store_commerce_webhook_events')) {
            return;
        }

        try {
            DB::table('store_commerce_webhook_events')->insert([
                'company_id' => $companyId,
                'provider' => $provider,
                'event_type' => $eventType,
                'external_id' => $externalId,
                'dedupe_key' => $dedupeKey,
                'payload_meta' => json_encode($meta),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::debug('Commerce webhook dedupe insert skipped', ['error' => $e->getMessage()]);
        }
    }
}
