<?php

namespace Tests\Unit\Collections;

use App\Enums\CollectionStatus;
use Tests\TestCase;

class CollectionStatusTest extends TestCase
{
    public function test_open_and_terminal_statuses(): void
    {
        $this->assertContains(CollectionStatus::PendingPin->value, CollectionStatus::open());
        $this->assertContains(CollectionStatus::Chasing->value, CollectionStatus::open());
        $this->assertNotContains(CollectionStatus::Paid->value, CollectionStatus::open());

        $this->assertTrue(CollectionStatus::Paid->isTerminal());
        $this->assertTrue(CollectionStatus::Cancelled->isTerminal());
        $this->assertFalse(CollectionStatus::Failed->isTerminal());
        $this->assertTrue(CollectionStatus::Failed->isOpen());
    }
}
