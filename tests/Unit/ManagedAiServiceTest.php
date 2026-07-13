<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use App\Services\Flowmaker\AiFlowAssistantService;
use App\Services\Platform\ManagedAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManagedAiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_allowance_reads_plan_config_not_plan_name(): void
    {
        $plan = Plans::create([
            'name' => 'Custom Plan Label',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 149,
            'period' => 1,
            'description' => 'Custom',
            'features' => 'Custom features',
        ]);
        $plan->setConfig('managed_ai_monthly_credits', '750');

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $status = app(ManagedAiService::class)->status($company);

        $this->assertSame(750, $status['monthly_allowance']);
        $this->assertSame(750, $status['remaining']);
    }

    public function test_credits_are_tracked_on_owner_not_company(): void
    {
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

        config(['managed-ai.platform_openrouter_api_key' => 'sk-platform-test']);

        $service = app(ManagedAiService::class);
        $service->consume($company, 10, 'ai_flow_generate');

        $this->assertSame('10', $owner->fresh()->getConfig('managed_ai_credits_used'));
        $this->assertSame('0', $company->fresh()->getConfig('managed_ai_credits_used', '0'));
        $this->assertSame(90, $service->remainingCredits($company));
    }

    public function test_byok_openrouter_does_not_consume_managed_credits(): void
    {
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

        $service = app(ManagedAiService::class);
        $service->consume($company, 5, 'ai_flow_generate');

        $this->assertSame('0', $owner->fresh()->getConfig('managed_ai_credits_used', '0'));
        $this->assertTrue($service->hasByokOpenRouter($company));
        $this->assertTrue($service->canPerformAction($company, 'ai_flow_generate'));
    }

    public function test_exhaustion_message_does_not_suggest_byok_when_already_using_byok(): void
    {
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

        $message = app(ManagedAiService::class)->exhaustionMessage($company);

        $this->assertStringNotContainsString('Add an OpenRouter key', $message);
        $this->assertStringNotContainsString('add your own OpenRouter', strtolower($message));
    }

    public function test_llm_generation_parses_openrouter_response(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'summary' => 'Booking flow draft',
                            'nodes' => [
                                [
                                    'id' => 'kw-1',
                                    'type' => 'keyword_trigger',
                                    'position' => ['x' => 0, 'y' => 0],
                                    'data' => [
                                        'keywords' => [['id' => 'kw1', 'value' => 'book', 'matchType' => 'contains']],
                                    ],
                                ],
                                [
                                    'id' => 'msg-1',
                                    'type' => 'message',
                                    'position' => ['x' => 300, 'y' => 0],
                                    'data' => ['settings' => ['message' => 'Welcome!']],
                                ],
                                [
                                    'id' => 'end-1',
                                    'type' => 'end',
                                    'position' => ['x' => 600, 'y' => 0],
                                    'data' => [],
                                ],
                            ],
                            'edges' => [
                                ['id' => 'e1', 'source' => 'kw-1', 'target' => 'msg-1', 'sourceHandle' => 'kw1'],
                                ['id' => 'e2', 'source' => 'msg-1', 'target' => 'end-1'],
                            ],
                        ]),
                    ],
                ]],
                'model' => 'openai/gpt-4o-mini',
            ], 200),
        ]);

        config(['managed-ai.platform_openrouter_api_key' => 'sk-test']);

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

        $draft = app(AiFlowAssistantService::class)->generate($company, 'Build a booking flow when someone says book');

        $this->assertSame('llm', $draft['source']);
        $this->assertCount(3, $draft['nodes']);
        $this->assertSame('5', $owner->fresh()->getConfig('managed_ai_credits_used'));
    }
}

class AiFlowAssistantRuleBasedTest extends TestCase
{
    public function test_rule_based_fallback_still_builds_payment_flow(): void
    {
        $draft = app(AiFlowAssistantService::class)->generateRuleBased('When customer says pay, collect M-Pesa payment');

        $this->assertNotEmpty($draft['nodes']);
        $this->assertTrue(
            collect($draft['nodes'])->contains(fn (array $node) => $node['type'] === 'request_payment')
        );
    }
}
