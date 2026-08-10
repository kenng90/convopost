<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\Remineder;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\SourceArchiveService;
use Modules\Wpbox\Models\Campaign;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemindersIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_notifications_list_loads_when_service_was_archived(): void
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

        $campaign = Campaign::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Appointment reminder',
            'is_reminder' => true,
            'variables' => '{}',
            'variables_match' => '{}',
        ]);

        Remineder::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_id' => $source->id,
            'campaign_id' => $campaign->id,
            'name' => 'Consultation — client reminder (before)',
            'type' => 1,
            'time' => 24,
            'time_type' => 'hours',
            'status' => 2,
            'is_service_managed' => true,
        ]);

        app(SourceArchiveService::class)->archive($source);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('reminders.reminders.index'));

        $response->assertOk();
        $response->assertDontSee('Consultation — client reminder (before)');
        $this->assertDatabaseMissing('reminders', [
            'company_id' => $company->id,
            'source_id' => $source->id,
        ]);
    }

    public function test_orphaned_managed_reminder_can_be_deleted_from_list(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        $source = Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Gone service',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'duration_options' => [30],
            'buffer_minutes' => 0,
            'timezone' => 'UTC',
            'min_notice_hours' => 0,
            'max_advance_days' => 30,
            'working_hours' => [],
        ]);

        $campaign = Campaign::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Appointment reminder',
            'is_reminder' => true,
            'variables' => '{}',
            'variables_match' => '{}',
        ]);

        $reminder = Remineder::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_id' => $source->id,
            'campaign_id' => $campaign->id,
            'name' => 'Gone service — client reminder (before)',
            'type' => 1,
            'time' => 24,
            'time_type' => 'hours',
            'status' => 1,
            'is_service_managed' => true,
        ]);

        // Soft-delete the service without going through archive (orphaned active rule).
        $source->delete();

        $list = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('reminders.reminders.index'));

        $list->assertOk();
        $list->assertSee(__('Orphaned'));
        $list->assertSee(route('reminders.reminders.delete', ['reminder' => $reminder->id]), false);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('reminders.reminders.delete', ['reminder' => $reminder->id]));

        $response->assertRedirect(route('reminders.reminders.index'));
        $this->assertDatabaseMissing('reminders', ['id' => $reminder->id]);
    }
}
