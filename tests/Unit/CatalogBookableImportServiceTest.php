<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use App\Services\Catalog\CatalogBookableImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Tests\TestCase;

class CatalogBookableImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private CatalogBookableImportService $service;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CatalogBookableImportService::class);
        $owner = User::factory()->create();
        $this->company = Company::factory()->create(['user_id' => $owner->id]);
    }

    public function test_supports_listing_and_service_modes_only(): void
    {
        $this->assertTrue($this->service->supportsMode('listing'));
        $this->assertTrue($this->service->supportsMode('service'));
        $this->assertFalse($this->service->supportsMode('commerce'));
        $this->assertFalse($this->service->supportsMode(null));
    }

    public function test_supports_bookable_import_is_false_for_jobs_vertical(): void
    {
        $this->assertTrue($this->service->supportsBookableImport('listing', 'real_estate'));
        $this->assertTrue($this->service->supportsBookableImport('listing', 'automotive'));
        $this->assertFalse($this->service->supportsBookableImport('listing', 'jobs'));
        $this->assertTrue($this->service->supportsBookableImport('service', 'general_service'));
    }

    public function test_strip_booking_metadata_from_items(): void
    {
        $items = [[
            'id' => 'job-1',
            'title' => 'Engineer',
            'metadata' => [
                'company' => 'Acme',
                'booking_source_id' => 99,
                'booking_source_name' => 'Interviews',
            ],
            'booking_source_id' => 99,
        ]];

        $result = $this->service->stripBookingMetadataFromItems($items);

        $this->assertSame('Acme', $result[0]['metadata']['company']);
        $this->assertArrayNotHasKey('booking_source_id', $result[0]['metadata']);
        $this->assertArrayNotHasKey('booking_source_name', $result[0]['metadata']);
        $this->assertArrayNotHasKey('booking_source_id', $result[0]);
    }

    public function test_parse_duration_minutes_handles_common_formats(): void
    {
        $this->assertSame(30, $this->service->parseDurationMinutes('30'));
        $this->assertSame(45, $this->service->parseDurationMinutes('45 min'));
        $this->assertSame(60, $this->service->parseDurationMinutes('1h'));
        $this->assertSame(90, $this->service->parseDurationMinutes('1h 30m'));
        $this->assertSame(30, $this->service->parseDurationMinutes('invalid', 30));
    }

    public function test_build_plan_suggests_link_for_matching_source_name(): void
    {
        $source = Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Haircut',
            'is_bookable' => true,
        ]);

        $plan = $this->service->buildPlan($this->company, [[
            'id' => 'svc-1',
            'title' => 'Haircut',
            'metadata' => [],
        ]]);

        $this->assertSame('link', $plan['rows'][0]['action']);
        $this->assertSame($source->id, $plan['rows'][0]['source_id']);
    }

    public function test_build_plan_suggests_create_when_no_match(): void
    {
        $plan = $this->service->buildPlan($this->company, [[
            'id' => 'svc-2',
            'title' => 'Massage',
            'metadata' => ['duration' => '45 min'],
        ]]);

        $this->assertSame('create', $plan['rows'][0]['action']);
        $this->assertSame('Massage', $plan['rows'][0]['name']);
        $this->assertSame(45, $plan['rows'][0]['duration_minutes']);
        $this->assertNull($plan['rows'][0]['source_id']);
    }

    public function test_build_plan_prefers_existing_item_link_on_reimport(): void
    {
        $source = Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Consultation',
            'is_bookable' => true,
        ]);

        $plan = $this->service->buildPlan(
            $this->company,
            [[
                'id' => 'svc-3',
                'title' => 'Brand New Title',
                'metadata' => [],
            ]],
            [[
                'id' => 'svc-3',
                'title' => 'Old Title',
                'metadata' => ['booking_source_id' => $source->id],
            ]]
        );

        $this->assertSame('link', $plan['rows'][0]['action']);
        $this->assertSame($source->id, $plan['rows'][0]['source_id']);
    }

    public function test_apply_plan_creates_links_and_skips(): void
    {
        $existing = Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Existing Service',
            'is_bookable' => true,
        ]);

        $staff = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Alex',
            'is_active' => true,
        ]);

        $items = [
            ['id' => 'a', 'title' => 'Create Me', 'metadata' => ['duration' => '60']],
            ['id' => 'b', 'title' => 'Link Me', 'metadata' => []],
            ['id' => 'c', 'title' => 'Skip Me', 'metadata' => ['booking_source_name' => 'Ignore']],
        ];

        $result = $this->service->applyPlan(
            $this->company,
            $items,
            [
                ['item_id' => 'a', 'action' => 'create', 'name' => 'Created Service', 'duration_minutes' => 60],
                ['item_id' => 'b', 'action' => 'link', 'source_id' => $existing->id],
                ['item_id' => 'c', 'action' => 'skip'],
            ],
            [
                'default_duration_minutes' => 30,
                'timezone' => 'Africa/Nairobi',
                'staff_ids' => [$staff->id],
            ]
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['linked']);
        $this->assertSame(1, $result['skipped']);

        $createdSource = Source::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('name', 'Created Service')
            ->first();

        $this->assertNotNull($createdSource);
        $this->assertSame(60, (int) $createdSource->default_duration_minutes);
        $this->assertSame('Africa/Nairobi', $createdSource->timezone);
        $this->assertTrue(
            SourceStaff::query()
                ->where('source_id', $createdSource->id)
                ->where('appointment_staff_id', $staff->id)
                ->exists()
        );

        $this->assertSame($createdSource->id, $result['items'][0]['metadata']['booking_source_id']);
        $this->assertSame($existing->id, $result['items'][1]['metadata']['booking_source_id']);
        $this->assertArrayNotHasKey('booking_source_id', $result['items'][2]['metadata']);
        $this->assertArrayNotHasKey('booking_source_name', $result['items'][2]['metadata']);
    }

    public function test_apply_plan_does_not_use_other_company_sources(): void
    {
        $otherOwner = User::factory()->create();
        $otherCompany = Company::factory()->create(['user_id' => $otherOwner->id]);
        $foreign = Source::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign',
            'is_bookable' => true,
        ]);

        $result = $this->service->applyPlan(
            $this->company,
            [['id' => 'x', 'title' => 'X', 'metadata' => []]],
            [['item_id' => 'x', 'action' => 'link', 'source_id' => $foreign->id]],
            [],
            'service'
        );

        $this->assertSame(1, $result['skipped']);
        $this->assertArrayNotHasKey('booking_source_id', $result['items'][0]['metadata']);
    }

    public function test_listing_build_plan_uses_shared_service_named_after_vertical(): void
    {
        $plan = $this->service->buildPlan(
            $this->company,
            [
                ['id' => 'car-1', 'title' => 'Toyota', 'metadata' => []],
                ['id' => 'car-2', 'title' => 'Honda', 'metadata' => []],
            ],
            [],
            'listing',
            'automotive'
        );

        $this->assertSame('shared', $plan['strategy']);
        $this->assertSame('create', $plan['shared']['action']);
        $this->assertSame('Automotive', $plan['shared']['name']);
        $this->assertSame(2, $plan['shared']['item_count']);
        $this->assertSame([], $plan['rows']);
    }

    public function test_listing_apply_plan_creates_one_source_for_all_items(): void
    {
        $items = [
            ['id' => 'h1', 'title' => 'House A', 'metadata' => []],
            ['id' => 'h2', 'title' => 'House B', 'metadata' => []],
            ['id' => 'h3', 'title' => 'House C', 'metadata' => []],
        ];

        $result = $this->service->applyPlan(
            $this->company,
            $items,
            [],
            ['default_duration_minutes' => 45, 'timezone' => 'UTC', 'staff_ids' => []],
            'listing',
            ['action' => 'create', 'name' => 'Real estate', 'duration_minutes' => 45],
            'real_estate'
        );

        $this->assertSame('shared', $result['strategy']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(3, $result['linked']);
        $this->assertSame(0, $result['skipped']);

        $sourceIds = collect($result['items'])
            ->map(fn ($item) => $item['metadata']['booking_source_id'] ?? null)
            ->unique()
            ->values();

        $this->assertCount(1, $sourceIds);
        $this->assertDatabaseCount('rem_res_sources', 1);
        $this->assertDatabaseHas('rem_res_sources', [
            'company_id' => $this->company->id,
            'name' => 'Real estate',
            'default_duration_minutes' => 45,
        ]);
    }
}
