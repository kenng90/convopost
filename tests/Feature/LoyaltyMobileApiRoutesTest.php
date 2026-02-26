<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoyaltyMobileApiRoutesTest extends TestCase
{
    /** @test */
    public function scan_route_exists()
    {
        $this->assertTrue(route('loyalty.scan.api') !== null);
    }

    /** @test */
    public function give_award_route_exists()
    {
        $this->assertTrue(route('loyalty.giveaward.api') !== null);
    }

    /** @test */
    public function give_points_route_exists()
    {
        $this->assertTrue(route('loyalty.givepoints.api') !== null);
    }

    /** @test */
    public function routes_have_correct_paths()
    {
        $this->assertStringContainsString('api/loyalty/scan', route('loyalty.scan.api', ['qrcontent' => '1|1']));
        $this->assertStringContainsString('api/loyalty/give_award', route('loyalty.giveaward.api', ['qrcontent' => '1|1']));
        $this->assertStringContainsString('api/loyalty/give_points', route('loyalty.givepoints.api'));
        $this->assertStringContainsString('api/loyalty/register_card', route('loyalty.registercard.api'));
    }
}


