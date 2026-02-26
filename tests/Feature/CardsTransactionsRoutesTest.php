<?php

namespace Tests\Feature;

use Tests\TestCase;

class CardsTransactionsRoutesTest extends TestCase
{
    /** @test */
    public function staff_transactions_route_exists()
    {
        $this->assertTrue(route('loyalty.movments.staff') !== null);
    }

    /** @test */
    public function owner_transactions_route_exists()
    {
        $this->assertTrue(route('loyalty.movments.owner') !== null);
    }

    /** @test */
    public function routes_have_correct_paths()
    {
        $this->assertStringContains('transactions/staff', route('loyalty.movments.staff'));
        $this->assertStringContains('transactions/owner', route('loyalty.movments.owner'));
    }
}
