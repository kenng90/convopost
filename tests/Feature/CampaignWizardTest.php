<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanCapability;
use App\Livewire\CampaignWizard;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Template;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampaignWizardTest extends TestCase
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

        config(['settings.enable_credits' => false]);
    }

    private function actingAsOwner()
    {
        return $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id]);
    }

    private function makeTemplate(): Template
    {
        return Template::create([
            'name' => 'hello_'.random_int(1, 9999),
            'language' => 'en',
            'status' => 'APPROVED',
            'company_id' => $this->company->id,
            'components' => json_encode([
                ['type' => 'BODY', 'text' => 'Hi {{1}}'],
            ]),
            'category' => 'MARKETING',
        ]);
    }

    public function test_broadcast_create_redirects_to_wizard(): void
    {
        $this->makeTemplate();

        $this->actingAsOwner()
            ->get(route('campaigns.create'))
            ->assertRedirect(route('campaigns.wizard', ['broadcast_type' => 'group']));
    }

    public function test_file_create_redirects_to_wizard_with_broadcast_type(): void
    {
        $this->makeTemplate();

        $this->actingAsOwner()
            ->get(route('campaigns.create', ['type' => 'file']))
            ->assertRedirect(route('campaigns.wizard', ['broadcast_type' => 'file']));
    }

    public function test_bot_create_keeps_legacy_flow(): void
    {
        $this->makeTemplate();

        $this->actingAsOwner()
            ->get(route('campaigns.create', ['type' => 'bot']))
            ->assertOk()
            ->assertSee('Create new template bot');
    }

    public function test_api_create_redirects_to_api_builder(): void
    {
        $this->makeTemplate();

        $this->actingAsOwner()
            ->get(route('campaigns.create', ['type' => 'api']))
            ->assertRedirect(route('wpbox.api.create'));
    }

    public function test_wizard_page_loads(): void
    {
        $this->makeTemplate();

        $this->actingAsOwner()
            ->get(route('campaigns.wizard'))
            ->assertOk()
            ->assertSeeLivewire(CampaignWizard::class);
    }

    public function test_wizard_saves_whatsapp_group_draft(): void
    {
        $template = $this->makeTemplate();
        Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '+254700000099',
            'subscribed' => 1,
        ]);

        $this->actingAsOwner();

        Livewire::test(CampaignWizard::class)
            ->set('name', 'Wizard draft')
            ->set('groupId', 0)
            ->set('templateId', $template->id)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('wa_campaings', [
            'name' => 'Wizard draft',
            'company_id' => $this->company->id,
            'status' => Campaign::STATUS_DRAFT,
            'channel' => Campaign::CHANNEL_WHATSAPP,
            'broadcast_type' => 'group',
        ]);
    }

    public function test_wizard_launches_whatsapp_campaign_with_contact_field_mapping_without_paramvalues_body(): void
    {
        $template = $this->makeTemplate();
        Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '+254700000077',
            'subscribed' => 1,
        ]);

        $this->actingAsOwner();

        Livewire::test(CampaignWizard::class)
            ->set('name', 'Contact field launch')
            ->set('groupId', 0)
            ->set('templateId', $template->id)
            ->set('parammatch', ['body' => ['1' => '-1']])
            ->call('launchCampaign')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('wa_campaings', [
            'name' => 'Contact field launch',
            'company_id' => $this->company->id,
            'status' => Campaign::STATUS_SENDING,
        ]);

        $this->assertDatabaseHas('messages', [
            'company_id' => $this->company->id,
            'value' => 'Hi Jane',
        ]);
    }

    public function test_wizard_saves_sms_draft_with_custom_body(): void
    {
        Contact::create([
            'company_id' => $this->company->id,
            'name' => 'Jane',
            'phone' => '+254700000088',
            'subscribed' => 1,
        ]);

        $this->actingAsOwner();

        Livewire::test(CampaignWizard::class)
            ->set('name', 'SMS draft')
            ->set('channel', Campaign::CHANNEL_SMS)
            ->set('channelTemplateKey', 'custom')
            ->set('smsBody', 'Hello from SMS')
            ->set('groupId', 0)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('wa_campaings', [
            'name' => 'SMS draft',
            'channel' => Campaign::CHANNEL_SMS,
            'channel_template_key' => 'custom',
            'status' => Campaign::STATUS_DRAFT,
        ]);
    }
}
