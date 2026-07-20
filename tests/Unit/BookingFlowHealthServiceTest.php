<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\Flowmaker\BookingFlowHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\Source;
use Tests\TestCase;

class BookingFlowHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_warns_when_dynamic_booking_has_no_bookable_services(): void
    {
        $company = Company::factory()->create();

        $warnings = app(BookingFlowHealthService::class)->validateForCompany(
            $company,
            $this->bookingFlowData()
        );

        $this->assertContains(
            'Book appointment node [book-1]: no bookable services configured in Reminders.',
            $warnings
        );
    }

    public function test_dynamic_service_pagination_does_not_produce_obsolete_list_limit_warning(): void
    {
        $company = Company::factory()->create();

        foreach (range(1, 11) as $index) {
            Source::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'name' => sprintf('Treatment %02d', $index),
                'is_bookable' => true,
                'default_duration_minutes' => 30,
                'duration_options' => [30],
                'timezone' => 'UTC',
            ]);
        }

        $warnings = app(BookingFlowHealthService::class)->validateForCompany(
            $company,
            $this->bookingFlowData()
        );

        $this->assertEmpty(array_filter(
            $warnings,
            fn (string $warning) => str_contains($warning, 'exceed WhatsApp list limit')
        ));
        $this->assertEmpty(array_filter(
            $warnings,
            fn (string $warning) => str_contains($warning, 'wire the Error output')
                || str_contains($warning, 'wire the Unavailable output')
        ));
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, string>>}
     */
    private function bookingFlowData(): array
    {
        return [
            'nodes' => [
                [
                    'id' => 'book-1',
                    'type' => 'book_appointment',
                    'data' => [
                        'settings' => [
                            'source_name' => '',
                            'duration_minutes' => '',
                        ],
                    ],
                ],
                ['id' => 'error-message', 'type' => 'message', 'data' => ['settings' => []]],
                ['id' => 'unavailable-message', 'type' => 'message', 'data' => ['settings' => []]],
            ],
            'edges' => [
                [
                    'id' => 'e-error',
                    'source' => 'book-1',
                    'target' => 'error-message',
                    'sourceHandle' => 'error',
                ],
                [
                    'id' => 'e-unavailable',
                    'source' => 'book-1',
                    'target' => 'unavailable-message',
                    'sourceHandle' => 'unavailable',
                ],
            ],
        ];
    }
}
