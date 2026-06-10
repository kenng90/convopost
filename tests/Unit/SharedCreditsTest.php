<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SharedCreditsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
        config(['settings.enable_credits' => true]);
    }

    public function test_owner_credits_are_shared_across_organizations(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $companyA = Company::factory()->create(['user_id' => $owner->id]);
        $companyB = Company::factory()->create(['user_id' => $owner->id]);

        $owner->addCredits(1000, 'manual-grant');

        $this->assertSame(1000, $owner->fresh()->getTotalRemainingCredits());
        $this->assertSame(1000, $companyA->fresh()->getTotalRemainingCredits());
        $this->assertSame(1000, $companyB->fresh()->getTotalRemainingCredits());

        $companyB->useCredits(250, 'send_regular_message');

        $this->assertSame(750, $owner->fresh()->getTotalRemainingCredits());
        $this->assertSame(750, $companyA->fresh()->getTotalRemainingCredits());
        $this->assertSame(750, $companyB->fresh()->getTotalRemainingCredits());
    }
}
