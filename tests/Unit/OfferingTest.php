<?php

namespace Tests\Unit;

use App\Support\Offering;
use Tests\TestCase;

class OfferingTest extends TestCase
{
    public function test_default_mode_is_social_commerce(): void
    {
        config(['offering.mode' => 'social_commerce']);

        $this->assertTrue(Offering::isSocialCommerce());
        $this->assertFalse(Offering::isFull());
        $this->assertFalse(Offering::whatsappEnabled());
        $this->assertTrue(Offering::whatsappDormant());
    }

    public function test_full_mode_unlocks_whatsapp(): void
    {
        config(['offering.mode' => 'full']);

        $this->assertFalse(Offering::isSocialCommerce());
        $this->assertTrue(Offering::isFull());
        $this->assertTrue(Offering::whatsappEnabled());
        $this->assertFalse(Offering::whatsappDormant());
    }

    public function test_invalid_mode_falls_back_to_social_commerce(): void
    {
        config(['offering.mode' => 'not-a-real-mode']);

        $this->assertSame(Offering::MODE_SOCIAL_COMMERCE, Offering::mode());
        $this->assertTrue(Offering::whatsappDormant());
    }

    public function test_dormant_module_and_route_checks_respect_mode(): void
    {
        config([
            'offering.mode' => 'social_commerce',
            'offering.whatsapp_dormant_modules' => ['whatsappcall', 'instagram'],
            'offering.whatsapp_dormant_routes' => ['chat.index', 'campaigns.index'],
        ]);

        $this->assertTrue(Offering::isDormantModule('whatsappcall'));
        $this->assertTrue(Offering::isDormantRoute('chat.index'));
        $this->assertFalse(Offering::isDormantModule('social'));
        $this->assertFalse(Offering::isDormantRoute('social.home'));

        config(['offering.mode' => 'full']);

        $this->assertFalse(Offering::isDormantModule('whatsappcall'));
        $this->assertFalse(Offering::isDormantRoute('chat.index'));
    }

    public function test_social_home_route_comes_from_config(): void
    {
        config(['offering.social_home_route' => 'social.calendar']);

        $this->assertSame('social.calendar', Offering::socialHomeRoute());
    }
}
