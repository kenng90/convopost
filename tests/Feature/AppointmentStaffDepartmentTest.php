<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Department;
use Tests\TestCase;

class AppointmentStaffDepartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_member_can_belong_to_multiple_departments(): void
    {
        $company = Company::factory()->create();

        $spa = Department::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Spa',
            'is_active' => true,
        ]);
        $clinic = Department::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Clinic',
            'is_active' => true,
        ]);

        $member = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'is_active' => true,
        ]);

        $member->departments()->sync([$spa->id, $clinic->id]);
        $member->update(['department_id' => $spa->id]);

        $member->refresh()->load('departments');

        $this->assertCount(2, $member->departments);
        $this->assertSame('Spa, Clinic', $member->departmentNamesLabel());
    }

    public function test_service_staff_options_include_members_from_pivot_departments(): void
    {
        $company = Company::factory()->create();

        $spa = Department::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Spa',
            'is_active' => true,
        ]);
        $other = Department::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Other',
            'is_active' => true,
        ]);

        $member = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Cross Dept',
            'email' => 'cross@example.com',
            'department_id' => $other->id,
            'is_active' => true,
        ]);
        $member->departments()->sync([$spa->id, $other->id]);

        $options = AppointmentStaff::query()
            ->where('is_active', true)
            ->where(function ($builder) use ($spa) {
                $builder->where('department_id', $spa->id)
                    ->orWhereHas('departments', fn ($relation) => $relation->where('rem_departments.id', $spa->id));
            })
            ->pluck('name', 'id');

        $this->assertArrayHasKey($member->id, $options->all());
    }
}
