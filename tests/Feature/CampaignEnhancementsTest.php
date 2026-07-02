<?php

namespace Tests\Feature;

use App\Console\Commands\CheckCampaignCompletion;
use App\Console\Commands\DispatchScheduledCampaigns;
use App\Http\Middleware\EnsurePlanCapability;
use App\Models\Company;
use App\Models\User;
use App\Services\Campaign\CampaignTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\CampaignTrigger;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Models\Template;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampaignEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
        $this->company = Company::factory()->create(['user_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);

        session(['company_id' => $this->company->id]);

        $this->withoutMiddleware([
            EnsurePlanCapability::class,
            \Modules\Wpbox\Http\Middleware\CheckPlan::class,
            \Modules\Wpbox\Http\Middleware\CheckCampaignPlanLimit::class,
        ]);
    }

    private function actingAsOwner()
    {
        return $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id]);
    }

    private function dispatchToken(): string
    {
        return hash('sha256', config('app.key').':campaign-dispatch');
    }

    private function makeContact(array $attrs = []): Contact
    {
        return Contact::create(array_merge([
            'company_id' => $this->company->id,
            'name' => 'Test Contact',
            'phone' => '+2547'.random_int(10000000, 99999999),
            'subscribed' => 1,
        ], $attrs));
    }

    private function makeTemplate(): Template
    {
        return Template::create([
            'name' => 'hello_'.random_int(1, 9999),
            'language' => 'en',
            'status' => 'APPROVED',
            'company_id' => $this->company->id,
            'components' => json_encode([['type' => 'BODY', 'text' => 'Hi']]),
            'category' => 'MARKETING',
        ]);
    }

    public function test_dispatch_endpoint_requires_token(): void
    {
        $this->get('/webhook/wpbox/sendschuduledmessages')->assertForbidden();
    }

    public function test_dispatch_endpoint_accepts_valid_token(): void
    {
        $this->get('/webhook/wpbox/sendschuduledmessages?token='.$this->dispatchToken())
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_dispatch_scheduled_command_runs(): void
    {
        $this->artisan(DispatchScheduledCampaigns::class)->assertSuccessful();
    }

    public function test_campaign_report_csv_uses_correct_status_comparison(): void
    {
        $template = $this->makeTemplate();

        $campaign = Campaign::create([
            'name' => 'Report test',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'broadcast_type' => 'group',
            'status' => Campaign::STATUS_COMPLETED,
        ]);

        $contact = $this->makeContact();

        Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'company_id' => $this->company->id,
            'status' => 2,
            'value' => 'hello',
        ]);

        $this->actingAsOwner()->get(route('campaigns.report', $campaign))->assertOk();
    }

    public function test_plan_usage_counts_only_broadcast_campaigns(): void
    {
        Campaign::create([
            'name' => 'Broadcast',
            'company_id' => $this->company->id,
            'broadcast_type' => 'group',
        ]);

        Campaign::create([
            'name' => 'Bot',
            'company_id' => $this->company->id,
            'is_bot' => true,
        ]);

        $usage = app(\App\Services\PlanUsageLimit::class)
            ->getUsageForCompany($this->company, app(\App\Services\PlanUsageLimit::class)->resolvePlanForCompany($this->company));

        $this->assertSame(1, $usage['campaigns']);
    }

    public function test_campaign_cancel_marks_pending_messages_cancelled(): void
    {
        $campaign = Campaign::create([
            'name' => 'Cancel me',
            'company_id' => $this->company->id,
            'status' => Campaign::STATUS_SENDING,
            'broadcast_type' => 'group',
        ]);

        $contact = $this->makeContact();

        Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'company_id' => $this->company->id,
            'status' => Message::STATUS_PENDING,
            'value' => 'pending',
            'scchuduled_at' => now(),
        ]);

        $this->actingAsOwner()
            ->get(route('campaigns.cancel', $campaign))
            ->assertRedirect();

        $this->assertSame(Campaign::STATUS_CANCELLED, $campaign->fresh()->status);
        $this->assertSame(Message::STATUS_CANCELLED, $campaign->messages()->first()->status);
    }

    public function test_campaign_clone_creates_draft(): void
    {
        $template = $this->makeTemplate();

        $campaign = Campaign::create([
            'name' => 'Original',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'broadcast_type' => 'group',
            'status' => Campaign::STATUS_COMPLETED,
        ]);

        $this->actingAsOwner()
            ->get(route('campaigns.clone', $campaign))
            ->assertRedirect();

        $this->assertDatabaseHas('wa_campaings', [
            'name' => 'Original (copy)',
            'status' => Campaign::STATUS_DRAFT,
            'cloned_from_id' => $campaign->id,
        ]);
    }

    public function test_campaign_webhook_dispatches_on_completion_check(): void
    {
        Http::fake();

        $this->company->setConfig('campaign_webhook_url', 'https://example.com/hook');

        $campaign = Campaign::create([
            'name' => 'Done',
            'company_id' => $this->company->id,
            'status' => Campaign::STATUS_SENDING,
            'send_to' => 1,
            'broadcast_type' => 'group',
        ]);

        $contact = $this->makeContact();

        Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'company_id' => $this->company->id,
            'status' => Message::STATUS_DELIVERED,
            'value' => 'sent',
        ]);

        Artisan::call(CheckCampaignCompletion::class);

        Http::assertSent(function ($request) use ($campaign) {
            return $request->url() === 'https://example.com/hook'
                && ($request['event'] ?? null) === 'campaign.completed'
                && ($request['data']['campaign_id'] ?? null) === $campaign->id;
        });

        $this->assertSame(Campaign::STATUS_COMPLETED, $campaign->fresh()->status);
    }

    public function test_store_event_triggers_api_campaign(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        config(['settings.enable_credits' => false]);

        $this->company->setConfig('whatsapp_phone_number_id', '123');
        $this->company->setConfig('whatsapp_permanent_access_token', 'token');

        $template = $this->makeTemplate();

        $apiCampaign = Campaign::create([
            'name' => 'API trigger',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'is_api' => true,
            'variables' => '{}',
            'variables_match' => '{}',
        ]);

        CampaignTrigger::create([
            'company_id' => $this->company->id,
            'event_type' => CampaignTriggerService::EVENT_ORDER_CREATED,
            'campaign_id' => $apiCampaign->id,
            'is_active' => true,
        ]);

        $sent = app(CampaignTriggerService::class)->fire(
            $this->company,
            CampaignTriggerService::EVENT_ORDER_CREATED,
            ['phone' => '+254700000001', 'customer_name' => 'Jane']
        );

        $this->assertSame(1, $sent);
        $this->assertDatabaseHas('messages', [
            'campaign_id' => $apiCampaign->id,
            'company_id' => $this->company->id,
        ]);
    }
}
