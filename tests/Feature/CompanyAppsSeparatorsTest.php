<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanPlugin;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyAppsSeparatorsTest extends TestCase
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

    public function test_company_apps_groups_vendor_fields_under_one_separator_tab(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.apps.company'));

        $response->assertOk();

        $separators = collect($response->viewData('separators'));

        $journeySeparators = $separators->where('snake', 'journeys');
        $this->assertCount(1, $journeySeparators);
        $this->assertCount(5, $journeySeparators->first()['fields']);

        $bookingSeparators = $separators->where('snake', 'bookings');
        $this->assertCount(1, $bookingSeparators);
        $this->assertCount(4, $bookingSeparators->first()['fields']);
    }
}
