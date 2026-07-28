<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanCapability;
use App\Jobs\Campaign\PrepareCampaignMessagesJob;
use App\Jobs\Campaign\SendCampaignMessageJob;
use App\Models\Company;
use App\Models\User;
use App\Services\Campaign\CampaignAudienceResolver;
use App\Services\Campaign\CampaignLaunchService;
use App\Services\Campaign\CampaignMessagePreparationService;
use App\Services\Campaign\CampaignRecipientGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Modules\Contacts\Models\Contact;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Template;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampaignScalabilityTest extends TestCase
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

        config([
            'settings.enable_credits' => false,
            'wpbox.campaign_max_recipients' => 100,
            'wpbox.campaign_preparation_chunk' => 2,
            'wpbox.campaign_insert_chunk' => 2,
            'wpbox.campaign_async_dispatch' => true,
            'queue.default' => 'redis',
        ]);
    }

    private function makeTemplate(): Template
    {
        return Template::create([
            'name' => 'hello_'.random_int(1, 9999),
            'language' => 'en',
            'status' => 'APPROVED',
            'company_id' => $this->company->id,
            'components' => json_encode([['type' => 'BODY', 'text' => 'Hi {{1}}']]),
            'category' => 'MARKETING',
        ]);
    }

    public function test_recipient_guard_rejects_campaigns_over_limit(): void
    {
        $this->expectException(ValidationException::class);

        app(CampaignRecipientGuard::class)->assertWithinLimit(101);
    }

    public function test_launch_queues_preparation_job_for_group_broadcast(): void
    {
        Queue::fake();

        $template = $this->makeTemplate();

        Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '254700000001',
            'subscribed' => 1,
        ]);

        $campaign = app(CampaignLaunchService::class)->launch($this->company, [
            'name' => 'Scalable campaign',
            'channel' => Campaign::CHANNEL_WHATSAPP,
            'broadcast_type' => 'group',
            'template_id' => $template->id,
            'send_now' => true,
            'paramvalues' => ['body' => ['1' => 'Jane']],
            'parammatch' => ['body' => ['1' => '-2']],
        ]);

        $this->assertSame(Campaign::STATUS_PREPARING, $campaign->status);
        Queue::assertPushed(PrepareCampaignMessagesJob::class, fn ($job) => $job->campaignId === $campaign->id);
    }

    public function test_preparation_service_builds_messages_in_chunks(): void
    {
        config(['queue.default' => 'sync']);

        $template = $this->makeTemplate();

        foreach (range(1, 5) as $index) {
            Contact::create([
                'company_id' => $this->company->id,
                'name' => 'Contact '.$index,
                'phone' => '25470000000'.$index,
                'subscribed' => 1,
            ]);
        }

        $campaign = Campaign::create([
            'name' => 'Chunk prep',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'channel' => Campaign::CHANNEL_WHATSAPP,
            'broadcast_type' => 'group',
            'status' => Campaign::STATUS_PREPARING,
            'variables' => json_encode(['body' => ['1' => 'Test']]),
            'variables_match' => json_encode(['body' => ['1' => '-2']]),
            'launch_payload' => [
                'send_now' => true,
                'paramvalues' => ['body' => ['1' => 'Test']],
                'parammatch' => ['body' => ['1' => '-2']],
            ],
        ]);

        app(CampaignMessagePreparationService::class)->prepare($campaign, $campaign->launch_payload);

        $campaign->refresh();

        $this->assertSame(Campaign::STATUS_SENDING, $campaign->status);
        $this->assertSame(5, $campaign->send_to);
        $this->assertDatabaseCount('messages', 5);
    }

    public function test_audience_resolver_uses_count_without_loading_all_contacts(): void
    {
        foreach (range(1, 3) as $index) {
            Contact::create([
                'company_id' => $this->company->id,
                'name' => 'Contact '.$index,
                'phone' => '25471111000'.$index,
                'subscribed' => 1,
            ]);
        }

        $count = app(CampaignAudienceResolver::class)->subscribedCount($this->company, []);
        $resolved = app(CampaignAudienceResolver::class)->resolve($this->company, []);

        $this->assertSame(3, $count);
        $this->assertLessThanOrEqual(500, $resolved['contacts']->count());
    }

    public function test_async_dispatch_enqueues_send_jobs(): void
    {
        Queue::fake();

        config(['queue.default' => 'redis']);

        $template = $this->makeTemplate();
        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '254700000099',
            'subscribed' => 1,
        ]);

        $campaign = Campaign::create([
            'name' => 'Dispatch test',
            'company_id' => $this->company->id,
            'template_id' => $template->id,
            'channel' => Campaign::CHANNEL_WHATSAPP,
            'broadcast_type' => 'group',
            'status' => Campaign::STATUS_SENDING,
            'is_active' => true,
            'send_to' => 1,
            'variables' => json_encode([]),
            'variables_match' => json_encode([]),
        ]);

        \Modules\Wpbox\Models\Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'company_id' => $this->company->id,
            'status' => \Modules\Wpbox\Models\Message::STATUS_PENDING,
            'scchuduled_at' => now()->subMinute(),
            'value' => 'Hi',
            'components' => '[]',
            'buttons' => '[]',
        ]);

        app(\App\Services\Campaign\CampaignDispatchService::class)->enqueuePendingBatch();

        Queue::assertPushed(SendCampaignMessageJob::class);
    }
}
