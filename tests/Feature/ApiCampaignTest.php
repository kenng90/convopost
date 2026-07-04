<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanCapability;
use App\Models\Company;
use App\Models\User;
use App\Services\Campaign\ApiCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Models\Template;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiCampaignTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
        $this->company = Company::factory()->create(['user_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);
        $this->token = $this->owner->createToken('api-campaign-test')->plainTextToken;

        session(['company_id' => $this->company->id]);

        $this->withoutMiddleware([
            EnsurePlanCapability::class,
            \Modules\Wpbox\Http\Middleware\CheckPlan::class,
            \Modules\Wpbox\Http\Middleware\CheckCampaignPlanLimit::class,
            \Modules\Wpbox\Http\Middleware\CheckAPIPlan::class,
        ]);

        config(['settings.enable_credits' => false]);
    }

    private function actingAsOwner()
    {
        return $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id]);
    }

    private function makeTemplate(): Template
    {
        return Template::create([
            'name' => 'api_hello_'.random_int(1, 9999),
            'language' => 'en',
            'status' => 'APPROVED',
            'company_id' => $this->company->id,
            'components' => json_encode([
                ['type' => 'BODY', 'text' => 'Hi {{1}}, order {{2}}'],
            ]),
            'category' => 'UTILITY',
        ]);
    }

    public function test_legacy_create_api_path_redirects_to_api_builder(): void
    {
        $this->makeTemplate();

        $this->actingAsOwner()
            ->get(route('campaigns.create', ['type' => 'api']))
            ->assertRedirect(route('wpbox.api.create'));
    }

    public function test_api_create_page_loads(): void
    {
        $this->makeTemplate();

        $this->actingAsOwner()
            ->get(route('wpbox.api.create'))
            ->assertOk()
            ->assertSee('Create new API campaign')
            ->assertDontSee('Send new campaign');
    }

    public function test_bot_create_keeps_legacy_form_not_wizard(): void
    {
        $this->makeTemplate();

        $this->actingAsOwner()
            ->get(route('campaigns.create', ['type' => 'bot']))
            ->assertOk()
            ->assertSee('Create new template bot');
    }

    public function test_store_creates_active_api_campaign_without_messages(): void
    {
        $template = $this->makeTemplate();

        $this->actingAsOwner()
            ->post(route('wpbox.api.store'), [
                'name' => 'Order update',
                'template_id' => $template->id,
                'type' => 'api',
                'paramvalues' => [
                    'body' => [
                        '1' => 'customer_name',
                        '2' => 'order.id',
                    ],
                ],
                'parammatch' => [
                    'body' => [
                        '1' => '-3',
                        '2' => '-3',
                    ],
                ],
            ])
            ->assertRedirect();

        $campaign = Campaign::where('name', 'Order update')->first();

        $this->assertNotNull($campaign);
        $this->assertTrue((bool) $campaign->is_api);
        $this->assertTrue((bool) $campaign->is_active);
        $this->assertSame(Campaign::STATUS_ACTIVE, $campaign->status);
        $this->assertNull($campaign->broadcast_type);
        $this->assertSame(0, $campaign->messages()->count());
    }

    public function test_sendcampaigns_rejects_non_api_campaign(): void
    {
        $template = $this->makeTemplate();

        $broadcast = Campaign::create([
            'name' => 'Broadcast',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'is_api' => false,
            'broadcast_type' => 'group',
            'status' => Campaign::STATUS_SENDING,
        ]);

        $this->postJson('/api/wpbox/sendcampaigns', [
            'token' => $this->token,
            'campaign_id' => $broadcast->id,
            'phone' => '+254700000001',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'campaign_id must reference an API campaign.']);
    }

    public function test_sendcampaigns_rejects_inactive_api_campaign(): void
    {
        $template = $this->makeTemplate();

        $campaign = Campaign::create([
            'name' => 'Inactive API',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'is_api' => true,
            'is_active' => false,
            'status' => Campaign::STATUS_INACTIVE,
            'variables' => json_encode(['body' => ['1' => 'customer_name']]),
            'variables_match' => json_encode(['body' => ['1' => '-3']]),
        ]);

        $this->postJson('/api/wpbox/sendcampaigns', [
            'token' => $this->token,
            'campaign_id' => $campaign->id,
            'phone' => '+254700000002',
            'data' => ['customer_name' => 'Jane'],
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'This API campaign is inactive.']);
    }

    public function test_sendcampaigns_requires_api_variable_paths(): void
    {
        $template = $this->makeTemplate();

        $campaign = Campaign::create([
            'name' => 'API vars',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'is_api' => true,
            'is_active' => true,
            'status' => Campaign::STATUS_ACTIVE,
            'variables' => json_encode(['body' => ['1' => 'customer_name', '2' => 'order.id']]),
            'variables_match' => json_encode(['body' => ['1' => '-3', '2' => '-3']]),
        ]);

        $this->postJson('/api/wpbox/sendcampaigns', [
            'token' => $this->token,
            'campaign_id' => $campaign->id,
            'phone' => '+254700000003',
            'data' => ['customer_name' => 'Jane'],
        ])->assertStatus(422)
            ->assertJsonPath('missing.0', 'order.id');
    }

    public function test_sendcampaigns_queues_message_for_api_campaign(): void
    {
        $template = $this->makeTemplate();

        $campaign = Campaign::create([
            'name' => 'API send',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'is_api' => true,
            'is_active' => true,
            'status' => Campaign::STATUS_ACTIVE,
            'variables' => json_encode(['body' => ['1' => 'customer_name', '2' => 'order.id']]),
            'variables_match' => json_encode(['body' => ['1' => '-3', '2' => '-3']]),
        ]);

        $this->postJson('/api/wpbox/sendcampaigns', [
            'token' => $this->token,
            'campaign_id' => $campaign->id,
            'phone' => '+254700000004',
            'data' => [
                'customer_name' => 'Jane',
                'order' => ['id' => '55'],
            ],
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('queued', true);

        $this->assertDatabaseHas('messages', [
            'campaign_id' => $campaign->id,
            'status' => Message::STATUS_PENDING,
        ]);
    }

    public function test_toggle_deactivates_api_campaign(): void
    {
        $template = $this->makeTemplate();

        $campaign = Campaign::create([
            'name' => 'Toggle me',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'is_api' => true,
            'is_active' => true,
            'status' => Campaign::STATUS_ACTIVE,
        ]);

        $this->actingAsOwner()
            ->get(route('wpbox.api.toggle', $campaign))
            ->assertRedirect(route('wpbox.api.index'));

        $campaign->refresh();
        $this->assertFalse((bool) $campaign->is_active);
        $this->assertSame(Campaign::STATUS_INACTIVE, $campaign->status);
    }

    public function test_show_page_displays_api_campaign_details(): void
    {
        $template = $this->makeTemplate();

        $campaign = Campaign::create([
            'name' => 'API show',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'is_api' => true,
            'is_active' => true,
            'status' => Campaign::STATUS_ACTIVE,
            'variables' => json_encode(['body' => ['1' => 'customer_name', '2' => 'order.id']]),
            'variables_match' => json_encode(['body' => ['1' => '-3', '2' => '-3']]),
        ]);

        $this->actingAsOwner()
            ->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('API campaign')
            ->assertSee((string) $campaign->id)
            ->assertSee('sendcampaigns')
            ->assertSee('order.id');
    }

    public function test_sample_payload_includes_api_paths(): void
    {
        $template = $this->makeTemplate();

        $campaign = Campaign::create([
            'name' => 'Sample',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'is_api' => true,
            'variables' => json_encode(['body' => ['1' => 'customer_name', '2' => 'order.id']]),
            'variables_match' => json_encode(['body' => ['1' => '-3', '2' => '-3']]),
        ]);

        $payload = app(ApiCampaignService::class)->samplePayload($campaign, 'tok');

        $this->assertSame($campaign->id, $payload['campaign_id']);
        $this->assertArrayHasKey('customer_name', $payload['data']);
        $this->assertArrayHasKey('order', $payload['data']);
    }
}
