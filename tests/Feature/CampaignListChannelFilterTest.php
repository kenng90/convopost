<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanCapability;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wpbox\Models\Campaign;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampaignListChannelFilterTest extends TestCase
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

    private function makeBroadcast(string $name, string $channel = Campaign::CHANNEL_WHATSAPP): Campaign
    {
        return Campaign::create([
            'name' => $name,
            'company_id' => $this->company->id,
            'channel' => $channel,
            'broadcast_type' => 'group',
            'status' => Campaign::STATUS_DRAFT,
        ]);
    }

    public function test_index_defaults_to_whatsapp_campaigns(): void
    {
        $this->makeBroadcast('WhatsApp campaign', Campaign::CHANNEL_WHATSAPP);
        $this->makeBroadcast('SMS campaign', Campaign::CHANNEL_SMS);

        $response = $this->actingAsOwner()->get(route('campaigns.index'));

        $response->assertOk();
        $response->assertSee('WhatsApp campaign');
        $response->assertDontSee('SMS campaign');
    }

    public function test_index_filters_sms_campaigns(): void
    {
        $this->makeBroadcast('WhatsApp campaign', Campaign::CHANNEL_WHATSAPP);
        $this->makeBroadcast('SMS campaign', Campaign::CHANNEL_SMS);

        $response = $this->actingAsOwner()->get(route('campaigns.index', ['channel' => Campaign::CHANNEL_SMS]));

        $response->assertOk();
        $response->assertSee('SMS campaign');
        $response->assertDontSee('WhatsApp campaign');
    }

    public function test_index_filters_email_campaigns(): void
    {
        $this->makeBroadcast('Email campaign', Campaign::CHANNEL_EMAIL);
        $this->makeBroadcast('WhatsApp campaign', Campaign::CHANNEL_WHATSAPP);

        $response = $this->actingAsOwner()->get(route('campaigns.index', ['channel' => Campaign::CHANNEL_EMAIL]));

        $response->assertOk();
        $response->assertSee('Email campaign');
        $response->assertDontSee('WhatsApp campaign');
    }

    public function test_index_shows_channel_tab_counts(): void
    {
        $this->makeBroadcast('WhatsApp one', Campaign::CHANNEL_WHATSAPP);
        $this->makeBroadcast('WhatsApp two', Campaign::CHANNEL_WHATSAPP);
        $this->makeBroadcast('SMS one', Campaign::CHANNEL_SMS);

        $response = $this->actingAsOwner()->get(route('campaigns.index'));

        $response->assertOk();
        $response->assertSee('WhatsApp');
        $response->assertSee('SMS');
        $response->assertSee('Email');
    }

    public function test_index_preserves_channel_when_filtering_by_status(): void
    {
        $this->makeBroadcast('SMS draft', Campaign::CHANNEL_SMS);
        Campaign::create([
            'name' => 'SMS completed',
            'company_id' => $this->company->id,
            'channel' => Campaign::CHANNEL_SMS,
            'broadcast_type' => 'group',
            'status' => Campaign::STATUS_COMPLETED,
        ]);

        $response = $this->actingAsOwner()->get(route('campaigns.index', [
            'channel' => Campaign::CHANNEL_SMS,
            'status' => Campaign::STATUS_DRAFT,
        ]));

        $response->assertOk();
        $response->assertSee('SMS draft');
        $response->assertDontSee('SMS completed');
    }

    public function test_send_new_campaign_link_includes_active_channel(): void
    {
        $response = $this->actingAsOwner()->get(route('campaigns.index', [
            'channel' => Campaign::CHANNEL_EMAIL,
        ]));

        $response->assertOk();
        $response->assertSee(route('campaigns.wizard', ['channel' => Campaign::CHANNEL_EMAIL]), false);
    }
}
