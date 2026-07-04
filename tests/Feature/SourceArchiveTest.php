<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SourceArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_with_reservations_can_be_archived_without_fk_error(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        $source = Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Consultation',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'duration_options' => [30],
            'buffer_minutes' => 0,
            'timezone' => 'UTC',
            'min_notice_hours' => 0,
            'max_advance_days' => 30,
            'working_hours' => [],
        ]);

        $contact = Contact::create([
            'company_id' => $company->id,
            'name' => 'Jane',
            'phone' => '+254700000200',
            'subscribed' => 1,
        ]);

        Reservation::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_id' => $source->id,
            'contact_id' => $contact->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay()->addHour(),
            'status' => 1,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('reminders.sources.delete', ['source' => $source->id]));

        $response->assertRedirect(route('reminders.sources.index'));
        $response->assertSessionHas('status');

        $this->assertSoftDeleted('rem_res_sources', ['id' => $source->id]);
        $this->assertDatabaseHas('rem_reservations', ['source_id' => $source->id]);
        $this->assertNull(Source::find($source->id));
        $this->assertNotNull(Source::withTrashed()->find($source->id));
    }

    public function test_archived_service_is_hidden_from_services_list(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        $source = Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'To Archive',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'duration_options' => [30],
            'buffer_minutes' => 0,
            'timezone' => 'UTC',
            'min_notice_hours' => 0,
            'max_advance_days' => 30,
            'working_hours' => [],
        ]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('reminders.sources.delete', ['source' => $source->id]))
            ->assertRedirect();

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('reminders.sources.index'));

        $response->assertOk();
        $response->assertDontSee('To Archive');
    }
}
