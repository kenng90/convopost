<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use App\Services\Platform\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, float> */
    private function fakeEmbeddingVector(): array
    {
        return array_fill(0, 8, 0.125);
    }

    private function fakeOpenRouterEmbeddingResponse(): array
    {
        return [
            'data' => [
                ['embedding' => $this->fakeEmbeddingVector()],
            ],
            'model' => 'openai/text-embedding-3-small',
        ];
    }

    public function test_platform_key_indexes_embedding_and_consumes_managed_ai_credits(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/embeddings' => Http::response($this->fakeOpenRouterEmbeddingResponse(), 200),
        ]);

        config(['managed-ai.platform_openrouter_api_key' => 'sk-platform-test']);

        $plan = Plans::create([
            'name' => 'Pro',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 149,
            'period' => 1,
            'description' => 'Pro',
            'features' => 'Pro',
        ]);
        $plan->setConfig('managed_ai_monthly_credits', '100');

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $embedding = app(EmbeddingService::class)->create($company, 'Knowledge base chunk text', 42, meterUsage: true);

        $this->assertSame($this->fakeEmbeddingVector(), $embedding);
        $this->assertSame('1', $owner->fresh()->getConfig('managed_ai_credits_used'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://openrouter.ai/api/v1/embeddings'
                && $request['model'] === 'openai/text-embedding-3-small'
                && $request->hasHeader('Authorization', 'Bearer sk-platform-test');
        });
    }

    public function test_byok_openrouter_indexes_without_consuming_managed_ai_credits(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/embeddings' => Http::response($this->fakeOpenRouterEmbeddingResponse(), 200),
        ]);

        config(['managed-ai.platform_openrouter_api_key' => 'sk-platform-test']);

        $plan = Plans::create([
            'name' => 'Pro',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 149,
            'period' => 1,
            'description' => 'Pro',
            'features' => 'Pro',
        ]);
        $plan->setConfig('managed_ai_monthly_credits', '100');

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $company->setConfig('openrouter_api_key', 'sk-byok-test');

        $embedding = app(EmbeddingService::class)->create($company, 'FAQ answer text', 7, meterUsage: true);

        $this->assertNotNull($embedding);
        $this->assertSame('0', $owner->fresh()->getConfig('managed_ai_credits_used', '0'));

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer sk-byok-test');
        });
    }

    public function test_runtime_search_embedding_uses_openrouter_without_metering(): void
    {
        Http::fake([
            'openrouter.ai/api/v1/embeddings' => Http::response($this->fakeOpenRouterEmbeddingResponse(), 200),
        ]);

        config(['managed-ai.platform_openrouter_api_key' => 'sk-platform-test']);

        $plan = Plans::create([
            'name' => 'Pro',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 149,
            'period' => 1,
            'description' => 'Pro',
            'features' => 'Pro',
        ]);
        $plan->setConfig('managed_ai_monthly_credits', '100');

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $embedding = app(EmbeddingService::class)->create($company, 'customer question', 99, meterUsage: false);

        $this->assertNotNull($embedding);
        $this->assertSame('0', $owner->fresh()->getConfig('managed_ai_credits_used', '0'));
    }

    public function test_returns_null_when_no_openrouter_key_is_available(): void
    {
        config(['managed-ai.platform_openrouter_api_key' => null]);

        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);

        Http::fake();

        $embedding = app(EmbeddingService::class)->create($company, 'text', 1);

        $this->assertNull($embedding);
        Http::assertNothingSent();
    }
}
