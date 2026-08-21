<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiDocsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_docs_sits_in_more_section_just_above_outcomes(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $sections = $owner->getOwnerNavigationSections();
        $more = collect($sections)->firstWhere('label', __('More'));

        $this->assertNotNull($more);

        $names = collect($more['menus'])->pluck('name')->values();
        $apiDocsIndex = $names->search('API Docs');
        $outcomesIndex = $names->search('Outcomes');

        $this->assertNotFalse($apiDocsIndex);
        $this->assertNotFalse($outcomesIndex);
        $this->assertSame($outcomesIndex - 1, $apiDocsIndex);

        $apiDocs = collect($more['menus'])->firstWhere('id', 'apiDocsMenu');
        $this->assertNotNull($apiDocs);
        $this->assertSame(['api.info', 'wpbox.api.index'], collect($apiDocs['menus'])->pluck('route')->all());
        $this->assertSame(['API info', 'API campaigns'], collect($apiDocs['menus'])->pluck('name')->all());

        $whatsappTeam = collect($owner->collectOwnerModuleMenus())->firstWhere('id', 'more');
        $this->assertNotContains('api.info', collect($whatsappTeam['menus'] ?? [])->pluck('route')->all());
    }
}
