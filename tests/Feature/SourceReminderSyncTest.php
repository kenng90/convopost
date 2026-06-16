<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\Remineder;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\SourceReminderSyncService;
use Modules\Wpbox\Models\Campaign;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SourceReminderSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_save_syncs_managed_client_notification_rules(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        $campaign = Campaign::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Appointment reminder',
            'is_reminder' => true,
            'variables' => '{}',
            'variables_match' => '{}',
        ]);

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
            'reminder_before_campaign_id' => $campaign->id,
            'reminder_before_value' => 24,
            'reminder_before_unit' => 'hours',
        ]);

        app(SourceReminderSyncService::class)->sync($source->fresh());

        $beforeRule = Remineder::withoutGlobalScopes()
            ->where('source_id', $source->id)
            ->where('type', 1)
            ->first();

        $this->assertNotNull($beforeRule);
        $this->assertTrue($beforeRule->isServiceManaged());
        $this->assertSame($campaign->id, $beforeRule->campaign_id);
        $this->assertSame(24, $beforeRule->time);
    }

    public function test_service_managed_reminder_delete_redirects_to_service_edit(): void
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

        $reminder = Remineder::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_id' => $source->id,
            'name' => 'Consultation — client reminder (before)',
            'type' => 1,
            'time' => 1,
            'time_type' => 'hours',
            'status' => 1,
            'campaign_id' => Campaign::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'name' => 'Reminder template',
                'is_reminder' => true,
                'variables' => '{}',
                'variables_match' => '{}',
            ])->id,
            'is_service_managed' => true,
        ]);

        $response = $this->actingAs($owner)->get(route('reminders.reminders.delete', ['reminder' => $reminder->id]));

        $response->assertRedirect(route('reminders.sources.edit', ['source' => $source->id]));
        $this->assertDatabaseHas('reminders', ['id' => $reminder->id]);
    }
}
