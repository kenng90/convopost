<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanPlugin;
use App\Models\Company;
use App\Models\User;
use App\Models\WhatsappFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Flowmaker\Models\Flow;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsappFlowUseInAutomationTest extends TestCase
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
        $this->company = Company::factory()->create(['user_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);
        $this->withoutMiddleware(EnsurePlanPlugin::class);
    }

    public function test_use_in_automation_creates_flow_for_live_form(): void
    {
        $form = WhatsappFlow::create([
            'company_id' => $this->company->id,
            'name' => 'Live Lead Form',
            'flow_json' => ['screens' => [['fields' => [['type' => 'text']]]]],
            'status' => 'published',
            'meta_flow_id' => 'META-LIVE-1',
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('whatsapp-flows.use-in-automation', ['id' => $form->id, 'recipe' => 'checkout']));

        $flow = Flow::withoutGlobalScopes()->where('company_id', $this->company->id)->latest('id')->first();
        $this->assertNotNull($flow);
        $this->assertSame('whatsapp_form_checkout', $flow->source_template);
        $response->assertRedirect(route('flowmaker.edit', $flow));
    }

    public function test_use_in_automation_defaults_to_collect_recipe(): void
    {
        $form = WhatsappFlow::create([
            'company_id' => $this->company->id,
            'name' => 'Generic Form',
            'flow_json' => ['screens' => [['fields' => [['type' => 'text', 'name' => 'note', 'label' => 'Note']]]]],
            'status' => 'published',
            'meta_flow_id' => 'META-GENERIC-1',
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('whatsapp-flows.use-in-automation', $form->id));

        $flow = Flow::withoutGlobalScopes()->where('company_id', $this->company->id)->latest('id')->first();
        $this->assertNotNull($flow);
        $this->assertSame('whatsapp_form_collect', $flow->source_template);
        $this->assertSame('Generic Form — Collect automation', $flow->name);

        $data = json_decode($flow->draft_flow_data, true);
        $formNode = collect($data['nodes'])->firstWhere('type', 'whatsapp_flow');
        $this->assertSame([], $formNode['data']['settings']['conditions']);
        $this->assertSame([], $formNode['data']['settings']['fieldMappings']);
        $this->assertTrue(
            collect($data['edges'])->contains(fn ($e) => ($e['sourceHandle'] ?? '') === 'onFlowCompleted' && ($e['target'] ?? '') === 'message-thanks')
        );
        $this->assertFalse(
            collect($data['nodes'])->contains(fn ($n) => ($n['id'] ?? '') === 'message-no-match')
        );

        $response->assertRedirect(route('flowmaker.edit', $flow));
    }

    public function test_use_in_automation_redirects_draft_to_builder(): void
    {
        $form = WhatsappFlow::create([
            'company_id' => $this->company->id,
            'name' => 'Draft Form',
            'flow_json' => ['screens' => [['fields' => [['type' => 'text']]]]],
            'status' => 'draft',
        ]);

        $before = Flow::withoutGlobalScopes()->count();

        $response = $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('whatsapp-flows.use-in-automation', $form->id));

        $this->assertSame($before, Flow::withoutGlobalScopes()->count());
        $response->assertRedirect(route('whatsapp-flows.edit', $form->id));
    }
}
