<?php

namespace Tests\Unit;

use App\Support\FlowBuilderScreenValidator;
use PHPUnit\Framework\TestCase;

class FlowBuilderScreenValidatorTest extends TestCase
{
    public function test_it_returns_no_errors_when_field_ids_are_unique(): void
    {
        $screens = [
            [
                'id' => 'BOOKING',
                'fields' => [
                    ['id' => 1, 'type' => 'date'],
                    ['id' => 2, 'type' => 'select'],
                    ['id' => 3, 'type' => 'footer'],
                ],
            ],
        ];

        $this->assertSame([], FlowBuilderScreenValidator::duplicateFieldIdErrors($screens));
    }

    public function test_it_detects_duplicate_field_ids_across_a_booking_screen(): void
    {
        $screens = [
            [
                'id' => 'BOOKING',
                'fields' => [
                    ['id' => 1, 'type' => 'date'],
                    ['id' => 1, 'type' => 'select'],
                    ['id' => 1, 'type' => 'footer'],
                ],
            ],
        ];

        $errors = FlowBuilderScreenValidator::duplicateFieldIdErrors($screens);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Duplicate component field ids', $errors[0]);
        $this->assertStringContainsString('1', $errors[0]);
    }
}
