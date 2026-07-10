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
        foreach ($journeySeparators->first()['fields'] as $field) {
            $this->assertNull($field['separator']);
        }

        $bookingSeparators = $separators->where('snake', 'bookings');
        $this->assertCount(1, $bookingSeparators);
        $this->assertCount(4, $bookingSeparators->first()['fields']);
        foreach ($bookingSeparators->first()['fields'] as $field) {
            $this->assertNull($field['separator']);
        }

        $twilioSeparators = $separators->where('snake', 'twilio_your_account');
        $this->assertCount(1, $twilioSeparators);
        $this->assertCount(3, $twilioSeparators->first()['fields']);
        $this->assertSame(
            ['TWILIO_ACCOUNT_SID', 'TWILIO_AUTH_TOKEN', 'TWILIO_FROM_NUMBER'],
            collect($twilioSeparators->first()['fields'])->pluck('id')->map(fn (string $id) => str_replace(['custom[', ']'], '', $id))->all()
        );
    }

    public function test_company_apps_does_not_repeat_section_headings_in_form_fields(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.apps.company'));

        $response->assertOk();
        $response->assertSee('Enable journeys', false);
        $response->assertSee('Staff can manage journeys', false);
        $this->assertSame(
            0,
            substr_count($response->getContent(), 'class="display-4 mb-0">Journeys</h4>')
        );
    }

    public function test_company_apps_separator_ids_are_css_safe(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.apps.company'));

        $response->assertOk();

        foreach ($response->viewData('separators') as $separator) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $separator['snake']);
            $response->assertSee('id="'.$separator['snake'].'"', false);
        }
    }
}
