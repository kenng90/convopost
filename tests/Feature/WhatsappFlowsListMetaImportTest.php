<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanPlugin;
use App\Livewire\WhatsappFlowsList;
use App\Models\Company;
use App\Models\User;
use App\Models\WhatsappFlow;
use App\Services\WhatsappMetaFlowSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsappFlowsListMetaImportTest extends TestCase
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

    public function test_meta_import_modal_lists_flows_from_meta(): void
    {
        $mock = Mockery::mock(WhatsappMetaFlowSyncService::class);
        $mock->shouldReceive('listMetaFlows')
            ->once()
            ->andReturn([
                'success' => true,
                'flows' => [[
                    'meta_flow_id' => 'META-999',
                    'name' => 'Lead Form',
                    'status' => 'PUBLISHED',
                    'linked' => false,
                    'local_flow_id' => null,
                ]],
            ]);
        $this->app->instance(WhatsappMetaFlowSyncService::class, $mock);

        Livewire::actingAs($this->owner)
            ->test(WhatsappFlowsList::class)
            ->call('openMetaImport')
            ->assertSet('showMetaImport', true)
            ->assertSet('metaFlows.0.name', 'Lead Form');
    }

    public function test_import_from_meta_creates_local_flow_record(): void
    {
        $imported = WhatsappFlow::create([
            'company_id' => $this->company->id,
            'name' => 'Imported Lead Form',
            'flow_json' => ['screens' => [['id' => 'WELCOME', 'fields' => []]]],
            'meta_flow_id' => 'META-999',
            'flow_source' => 'meta_linked',
            'status' => 'published',
        ]);

        $mock = Mockery::mock(WhatsappMetaFlowSyncService::class);
        $mock->shouldReceive('importOrRefreshFromMeta')
            ->once()
            ->with(Mockery::type(Company::class), 'META-999')
            ->andReturn([
                'success' => true,
                'flow' => $imported,
                'message' => 'Flow synced from Meta.',
            ]);
        $mock->shouldReceive('listMetaFlows')
            ->twice()
            ->andReturn(['success' => true, 'flows' => []]);
        $this->app->instance(WhatsappMetaFlowSyncService::class, $mock);

        Livewire::actingAs($this->owner)
            ->test(WhatsappFlowsList::class)
            ->call('openMetaImport')
            ->call('importFromMeta', 'META-999')
            ->assertDispatched('showNotification');
    }
}
