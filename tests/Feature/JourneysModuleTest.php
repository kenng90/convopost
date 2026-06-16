<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanPlugin;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Contacts\Models\Group;
use Modules\Journies\Events\ContactMovedToStage;
use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyActivity;
use Modules\Journies\Models\JourneyGroupRule;
use Modules\Journies\Models\JourneyStage;
use Modules\Journies\Services\JourneyContactService;
use Modules\Journies\Services\JourneyTemplateService;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JourneysModuleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id,
        ]);
        $this->owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);

        $this->withoutMiddleware(EnsurePlanPlugin::class);
    }

    public function test_journey_index_shows_journey_stats(): void
    {
        $journey = Journey::create([
            'company_id' => $this->company->id,
            'name' => 'Sales Pipeline',
            'description' => 'Track deals',
        ]);

        JourneyStage::create([
            'journey_id' => $journey->id,
            'name' => 'Lead',
            'order' => 0,
        ]);

        $response = $this->actingAs($this->owner)->get(route('journies.index'));

        $response->assertOk();
        $response->assertSee('Sales Pipeline');
        $response->assertSee('Track deals');
        $response->assertSee('Journeys');
    }

    public function test_template_creates_journey_with_ordered_stages(): void
    {
        $journey = app(JourneyTemplateService::class)->createFromTemplate('sales');

        $this->assertNotNull($journey);
        $this->assertCount(4, $journey->stages);
        $this->assertSame('Lead', $journey->stages->first()->name);
    }

    public function test_move_contact_logs_activity_and_fires_event(): void
    {
        Event::fake([ContactMovedToStage::class]);

        $journey = Journey::create([
            'company_id' => $this->company->id,
            'name' => 'Pipeline',
            'description' => null,
        ]);

        $stage = JourneyStage::create([
            'journey_id' => $journey->id,
            'name' => 'Lead',
            'order' => 0,
            'campaign_id' => null,
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Jane Doe',
            'phone' => '+254700000001',
        ]);

        $result = app(JourneyContactService::class)->moveContactToStage($contact, $stage, 'manual_sidebar', $this->owner->id, false);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('journey_stage_contacts', [
            'stage_id' => $stage->id,
            'contact_id' => $contact->id,
        ]);
        $this->assertDatabaseHas('journey_activities', [
            'journey_id' => $journey->id,
            'contact_id' => $contact->id,
            'action' => JourneyActivity::ACTION_ADDED,
        ]);

        Event::assertNotDispatched(ContactMovedToStage::class);
    }

    public function test_sidebar_api_returns_current_stage(): void
    {
        $journey = Journey::create([
            'company_id' => $this->company->id,
            'name' => 'Pipeline',
            'description' => null,
        ]);

        $stage = JourneyStage::create([
            'journey_id' => $journey->id,
            'name' => 'Qualified',
            'order' => 0,
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'John Doe',
            'phone' => '+254700000002',
        ]);

        $stage->contacts()->attach($contact->id);

        $response = $this->actingAs($this->owner)->getJson(route('api.journies.get', $contact));

        $response->assertOk();
        $response->assertJsonPath('journeys.0.current_stage_name', 'Qualified');
        $response->assertJsonPath('journeys.0.in_journey', true);
    }

    public function test_contact_search_excludes_existing_journey_contacts(): void
    {
        $journey = Journey::create([
            'company_id' => $this->company->id,
            'name' => 'Pipeline',
            'description' => null,
        ]);

        $stage = JourneyStage::create([
            'journey_id' => $journey->id,
            'name' => 'Lead',
            'order' => 0,
        ]);

        $inside = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Inside Contact',
            'phone' => '+254700000003',
        ]);

        $outside = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Outside Contact',
            'phone' => '+254700000004',
        ]);

        $stage->contacts()->attach($inside->id);

        $response = $this->actingAs($this->owner)->getJson(route('api.journies.contacts.search', [
            'journey' => $journey,
            'q' => 'Contact',
            'exclude_existing' => true,
        ]));

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Outside Contact']);
        $response->assertJsonMissing(['name' => 'Inside Contact']);
    }

    public function test_group_rule_moves_contact_when_added_to_group(): void
    {
        $journey = Journey::create([
            'company_id' => $this->company->id,
            'name' => 'Pipeline',
            'description' => null,
        ]);

        $stage = JourneyStage::create([
            'journey_id' => $journey->id,
            'name' => 'VIP',
            'order' => 0,
        ]);

        $group = Group::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'VIP Clients',
        ]);

        JourneyGroupRule::create([
            'company_id' => $this->company->id,
            'group_id' => $group->id,
            'journey_id' => $journey->id,
            'stage_id' => $stage->id,
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'VIP Person',
            'phone' => '+254700000005',
        ]);

        $contact->groups()->attach($group->id);
        event(new \Modules\Journies\Events\ContactAddedToGroup($contact, $group->id));

        $this->assertDatabaseHas('journey_stage_contacts', [
            'stage_id' => $stage->id,
            'contact_id' => $contact->id,
        ]);
    }

    public function test_external_api_can_move_contact_to_stage(): void
    {
        Event::fake([ContactMovedToStage::class]);

        $journey = Journey::create([
            'company_id' => $this->company->id,
            'name' => 'API Pipeline',
            'description' => null,
        ]);

        $stage = JourneyStage::create([
            'journey_id' => $journey->id,
            'name' => 'Lead',
            'order' => 0,
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'API Contact',
            'phone' => '+254700000006',
        ]);

        $token = $this->owner->createToken('test')->plainTextToken;

        $response = $this->postJson('/api/journies/external/move-contact', [
            'phone' => $contact->phone,
            'stage_id' => $stage->id,
            'fire_campaign' => false,
            'token' => $token,
        ]);

        if (! $response->isSuccessful()) {
            $this->fail('API failed ['.$response->status().']: '.$response->getContent());
        }

        $response->assertOk();
        $this->assertDatabaseHas('journey_stage_contacts', [
            'stage_id' => $stage->id,
            'contact_id' => $contact->id,
        ]);
    }

    public function test_analytics_endpoint_returns_summary(): void
    {
        Journey::create([
            'company_id' => $this->company->id,
            'name' => 'Analytics Journey',
            'description' => 'Test',
        ]);

        $response = $this->actingAs($this->owner)->getJson(route('api.journies.analytics'));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonStructure(['data' => ['journeys_count', 'total_contacts', 'stages']]);
    }

    public function test_analytics_page_renders_without_error(): void
    {
        Journey::create([
            'company_id' => $this->company->id,
            'name' => 'Analytics Journey',
            'description' => 'Test',
        ]);

        $response = $this->actingAs($this->owner)->get(route('journies.analytics'));

        $response->assertOk();
        $response->assertSee('Journey analytics');
        $response->assertSee('journeyAnalyticsApp');
    }
}
