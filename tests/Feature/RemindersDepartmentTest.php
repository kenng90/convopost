<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\Department;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemindersDepartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_active_flag_is_persisted_on_update(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create([
            'user_id' => $owner->id,
        ]);
        $owner->update(['company_id' => $company->id]);

        session(['company_id' => $company->id]);

        $department = Department::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Massage',
            'is_active' => false,
        ]);

        $response = $this->actingAs($owner)->put(
            route('reminders.departments.update', ['department' => $department->id]),
            [
                'name' => 'Massage',
                'description' => 'Therapy room',
                'is_active' => '1',
            ]
        );

        $response->assertRedirect(route('reminders.departments.index'));

        $department->refresh();

        $this->assertTrue($department->is_active);
    }

    public function test_department_active_unset_is_persisted_on_update(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create([
            'user_id' => $owner->id,
        ]);
        $owner->update(['company_id' => $company->id]);

        session(['company_id' => $company->id]);

        $department = Department::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Massage',
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->put(
            route('reminders.departments.update', ['department' => $department->id]),
            [
                'name' => 'Massage',
                'description' => 'Therapy room',
            ]
        );

        $response->assertRedirect(route('reminders.departments.index'));

        $department->refresh();

        $this->assertFalse($department->is_active);
    }
}
