<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanCapability;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampaignShowTest extends TestCase
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

    public function test_show_page_displays_sms_campaign_content(): void
    {
        $campaign = Campaign::create([
            'name' => 'SMS show test',
            'company_id' => $this->company->id,
            'channel' => Campaign::CHANNEL_SMS,
            'channel_template_key' => 'custom',
            'broadcast_type' => 'group',
            'status' => Campaign::STATUS_COMPLETED,
            'variables' => json_encode(['sms_body' => 'Promo text here']),
            'send_to' => 1,
        ]);

        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '+254700000010',
            'subscribed' => 1,
        ]);

        Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'company_id' => $this->company->id,
            'status' => Message::STATUS_SENT,
            'value' => 'Promo text here',
        ]);

        $this->actingAsOwner()
            ->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('SMS')
            ->assertSee('Promo text here')
            ->assertSee('Jane')
            ->assertDontSee('Unknown template');
    }

    public function test_show_page_displays_email_subject_column(): void
    {
        $campaign = Campaign::create([
            'name' => 'Email show test',
            'company_id' => $this->company->id,
            'channel' => Campaign::CHANNEL_EMAIL,
            'broadcast_type' => 'group',
            'status' => Campaign::STATUS_COMPLETED,
            'variables' => json_encode([
                'email_subject' => 'Monthly update',
                'email_body' => 'Hello there',
            ]),
            'send_to' => 1,
        ]);

        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '+254700000011',
            'email' => 'jane@example.com',
            'subscribed' => 1,
        ]);

        Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'company_id' => $this->company->id,
            'status' => Message::STATUS_SENT,
            'value' => 'Hello there',
            'header_text' => 'Monthly update',
        ]);

        $this->actingAsOwner()
            ->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Email')
            ->assertSee('Monthly update')
            ->assertSee('jane@example.com');
    }

    public function test_report_csv_includes_email_columns(): void
    {
        $campaign = Campaign::create([
            'name' => 'Email report',
            'company_id' => $this->company->id,
            'channel' => Campaign::CHANNEL_EMAIL,
            'broadcast_type' => 'group',
            'variables' => json_encode([
                'email_subject' => 'Subject',
                'email_body' => 'Body',
            ]),
        ]);

        $contact = Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '+254700000012',
            'email' => 'jane@example.com',
            'subscribed' => 1,
        ]);

        Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'company_id' => $this->company->id,
            'status' => Message::STATUS_SENT,
            'value' => 'Body',
            'header_text' => 'Subject',
        ]);

        $response = $this->actingAsOwner()
            ->get(route('campaigns.report', $campaign));

        $response->assertOk();
        $content = $response->getFile()->getContent();
        $this->assertStringContainsString('Email', $content);
        $this->assertStringContainsString('Subject', $content);
        $this->assertStringContainsString('jane@example.com', $content);
    }
}
