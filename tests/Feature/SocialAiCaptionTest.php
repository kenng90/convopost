<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Social\Livewire\PostComposer;
use Modules\Social\Services\SocialAiCaptionService;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialAiCaptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        config([
            'settings.forceUserToPay' => false,
            'settings.enable_credits' => true,
            'managed-ai.enabled' => true,
            'managed-ai.platform_openrouter_api_key' => 'sk-platform-test',
        ]);
    }

    public function test_generate_requires_social_ai_capability(): void
    {
        [$company] = $this->companyWithPlan(['social_publish'], 100);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not included in your plan');

        app(SocialAiCaptionService::class)->generate($company, 'Weekend sale');
    }

    public function test_generate_blocks_when_managed_credits_exhausted(): void
    {
        [$company, $owner] = $this->companyWithPlan(['social_ai'], 5);
        $owner->setConfig('managed_ai_credits_used', '5');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exhausted');

        app(SocialAiCaptionService::class)->generate($company, 'Weekend sale');
    }

    public function test_generate_debited_managed_credits_and_returns_caption(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openai/gpt-4o-mini',
                'choices' => [
                    ['message' => ['content' => 'Shop the weekend deal in Nairobi.']],
                ],
                'usage' => [],
            ], 200),
        ]);

        [$company, $owner] = $this->companyWithPlan(['social_ai'], 100);

        $caption = app(SocialAiCaptionService::class)->generate($company, 'Weekend Nairobi sale');

        $this->assertSame('Shop the weekend deal in Nairobi.', $caption);
        $this->assertSame('2', $owner->fresh()->getConfig('managed_ai_credits_used'));
    }

    public function test_rewrite_debited_one_credit(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openai/gpt-4o-mini',
                'choices' => [
                    ['message' => ['content' => 'Punchy rewrite ready.']],
                ],
                'usage' => [],
            ], 200),
        ]);

        [$company, $owner] = $this->companyWithPlan(['social_ai'], 50);

        $caption = app(SocialAiCaptionService::class)->rewrite(
            $company,
            'Original caption about shoes',
            'punchy'
        );

        $this->assertSame('Punchy rewrite ready.', $caption);
        $this->assertSame('1', $owner->fresh()->getConfig('managed_ai_credits_used'));
    }

    public function test_composer_generate_sets_content_when_capable(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Livewire generated caption']],
                ],
            ], 200),
        ]);

        [$company, $owner] = $this->companyWithPlan(['social_ai'], 100);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id]);

        Livewire::test(PostComposer::class)
            ->set('aiPrompt', 'Launch offer')
            ->call('generateAiCaption')
            ->assertSet('content', 'Livewire generated caption')
            ->assertSet('aiError', null);
    }

    public function test_credit_actions_include_social_ai(): void
    {
        $actions = collect(config('credit-actions.actions'))->pluck('action');

        $this->assertTrue($actions->contains('social_ai_caption'));
        $this->assertTrue($actions->contains('social_ai_rewrite'));
    }

    /**
     * @param  list<string>  $capabilities
     * @return array{0: Company, 1: User, 2: Plans}
     */
    private function companyWithPlan(array $capabilities, int $aiCredits): array
    {
        $plan = Plans::create([
            'name' => 'Social AI Test '.uniqid(),
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 149,
            'period' => 1,
            'description' => 'Test',
            'features' => 'Test',
        ]);
        $plan->setConfig('capabilities', json_encode($capabilities));
        $plan->setConfig('managed_ai_monthly_credits', (string) $aiCredits);
        $plan->setConfig('plugins', json_encode(['social']));

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        $company = Company::factory()->create([
            'user_id' => $owner->id,
            'active' => 1,
        ]);
        $owner->update(['company_id' => $company->id]);

        return [$company, $owner, $plan];
    }
}
