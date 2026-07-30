<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Reminders\Models\Source;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogBookableImportWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_preview_excel_includes_bookable_plan_for_service_mode(): void
    {
        [$owner, $company] = $this->actingOwner();

        Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Haircut',
            'is_bookable' => true,
        ]);

        $file = $this->makeServiceSpreadsheet([
            ['SVC1', 'Haircut', 'Classic cut', '500', 'Hair', '', '30 min', 'Available', ''],
            ['SVC2', 'Massage', 'Relaxing', '2000', 'Spa', '', '60 min', 'Available', ''],
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('catalogs.preview-excel'), [
                'file' => $file,
                'catalog_mode' => CatalogMode::SERVICE,
                'vertical' => 'general_service',
                'include_bookable_plan' => 1,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'bookable_plan' => [
                'rows',
                'sources',
                'staff',
                'defaults',
            ],
        ]);

        $rows = collect($response->json('bookable_plan.rows'));
        $this->assertSame('link', $rows->firstWhere('item_id', 'SVC1')['action']);
        $this->assertSame('create', $rows->firstWhere('item_id', 'SVC2')['action']);
    }

    public function test_import_excel_creates_and_links_bookable_sources(): void
    {
        [$owner, $company] = $this->actingOwner();

        $existing = Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Haircut',
            'is_bookable' => true,
        ]);

        $file = $this->makeServiceSpreadsheet([
            ['SVC1', 'Haircut', 'Classic cut', '500', 'Hair', '', '30 min', 'Available', ''],
            ['SVC2', 'Massage', 'Relaxing', '2000', 'Spa', '', '60 min', 'Available', ''],
            ['SVC3', 'Showcase Only', 'No booking', '100', 'Other', '', '15 min', 'Available', ''],
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('catalogs.import-excel'), [
                'file' => $file,
                'catalogName' => 'Spa Menu',
                'catalog_mode' => CatalogMode::SERVICE,
                'vertical' => 'general_service',
                'booking_defaults' => json_encode([
                    'default_duration_minutes' => 30,
                    'timezone' => 'Africa/Nairobi',
                    'staff_ids' => [],
                ]),
                'booking_decisions' => json_encode([
                    ['item_id' => 'SVC1', 'action' => 'link', 'source_id' => $existing->id, 'name' => 'Haircut'],
                    ['item_id' => 'SVC2', 'action' => 'create', 'name' => 'Massage', 'duration_minutes' => 60],
                    ['item_id' => 'SVC3', 'action' => 'skip', 'name' => 'Showcase Only'],
                ]),
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('booking_stats.created', 1);
        $response->assertJsonPath('booking_stats.linked', 1);
        $response->assertJsonPath('booking_stats.skipped', 1);

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('name', 'Spa Menu')
            ->first();

        $this->assertNotNull($catalog);
        $items = collect($catalog->items)->keyBy('id');
        $this->assertSame($existing->id, $items['SVC1']['metadata']['booking_source_id']);
        $this->assertNotEmpty($items['SVC2']['metadata']['booking_source_id']);
        $this->assertArrayNotHasKey('booking_source_id', $items['SVC3']['metadata'] ?? []);

        $this->assertDatabaseHas('rem_res_sources', [
            'company_id' => $company->id,
            'name' => 'Massage',
            'is_bookable' => 1,
        ]);
    }

    public function test_commerce_import_ignores_booking_plan(): void
    {
        [$owner, $company] = $this->actingOwner();

        $file = $this->makeCommerceSpreadsheet([
            ['P1', 'Widget', 'A widget', '100', 'Goods', '', 'In Stock', '', ''],
        ]);

        $before = Source::withoutGlobalScopes()->where('company_id', $company->id)->count();

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('catalogs.import-excel'), [
                'file' => $file,
                'catalogName' => 'Retail Shop',
                'catalog_mode' => CatalogMode::COMMERCE,
                'vertical' => 'retail',
                'booking_decisions' => json_encode([
                    ['item_id' => 'P1', 'action' => 'create', 'name' => 'Should Not Create'],
                ]),
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('booking_stats', null);

        $this->assertSame(
            $before,
            Source::withoutGlobalScopes()->where('company_id', $company->id)->count()
        );
    }

    public function test_listing_import_creates_one_shared_bookable_service(): void
    {
        [$owner, $company] = $this->actingOwner();

        $file = $this->makeListingSpreadsheet([
            ['CAR1', 'Toyota RAV4', 'SUV', '3200000', 'SUV', '', 'Nairobi', '', '', 'Available', ''],
            ['CAR2', 'Honda Fit', 'Hatch', '900000', 'Hatchback', '', 'Mombasa', '', '', 'Available', ''],
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('catalogs.import-excel'), [
                'file' => $file,
                'catalogName' => 'Car Yard',
                'catalog_mode' => CatalogMode::LISTING,
                'vertical' => 'automotive',
                'booking_defaults' => json_encode([
                    'default_duration_minutes' => 30,
                    'timezone' => 'UTC',
                    'staff_ids' => [],
                ]),
                'booking_shared' => json_encode([
                    'action' => 'create',
                    'name' => 'Automotive',
                    'duration_minutes' => 30,
                ]),
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('booking_stats.strategy', 'shared');
        $response->assertJsonPath('booking_stats.created', 1);
        $response->assertJsonPath('booking_stats.linked', 2);

        $this->assertSame(
            1,
            Source::withoutGlobalScopes()->where('company_id', $company->id)->count()
        );
        $this->assertDatabaseHas('rem_res_sources', [
            'company_id' => $company->id,
            'name' => 'Automotive',
        ]);

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('name', 'Car Yard')
            ->first();
        $this->assertNotNull($catalog);

        $sourceIds = collect($catalog->items)
            ->map(fn ($item) => $item['metadata']['booking_source_id'] ?? null)
            ->unique()
            ->values();
        $this->assertCount(1, $sourceIds);
        $this->assertNotNull($sourceIds[0]);
    }

    public function test_jobs_preview_excel_omits_bookable_plan(): void
    {
        [$owner, $company] = $this->actingOwner();

        $file = $this->makeJobsSpreadsheet([
            ['JOB1', 'Sales Rep', 'Retail sales role', '', 'Sales', '', '', 'Acme Ltd', 'Full-time', 'KES 45000', '2 years', 'Diploma', 'Sales, CRM', 'Nairobi', '2026-08-01', 'Medical cover', 'careers@acme.example', 'Open'],
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('catalogs.preview-excel'), [
                'file' => $file,
                'catalog_mode' => CatalogMode::LISTING,
                'vertical' => 'jobs',
                'include_bookable_plan' => 1,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonMissingPath('bookable_plan');
    }

    public function test_jobs_import_does_not_create_bookable_sources(): void
    {
        [$owner, $company] = $this->actingOwner();

        $file = $this->makeJobsSpreadsheet([
            ['JOB1', 'Sales Rep', 'Retail sales role', '', 'Sales', '', '', 'Acme Ltd', 'Full-time', 'KES 45000', '2 years', 'Diploma', 'Sales, CRM', 'Nairobi', '2026-08-01', 'Medical cover', 'careers@acme.example', 'Open'],
            ['JOB2', 'Driver', 'Delivery driver', '', 'Ops', '', '', 'QuickDrop', 'Contract', 'Commission', 'License', '', 'Driving', 'Nairobi', '2026-08-15', '', '', 'Open'],
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('catalogs.import-excel'), [
                'file' => $file,
                'catalogName' => 'Careers Board',
                'catalog_mode' => CatalogMode::LISTING,
                'vertical' => 'jobs',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('booking_stats', null);

        $this->assertSame(
            0,
            Source::withoutGlobalScopes()->where('company_id', $company->id)->count()
        );

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('name', 'Careers Board')
            ->first();

        $this->assertNotNull($catalog);
        foreach ($catalog->items as $item) {
            $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
            $this->assertEmpty($metadata['booking_source_id'] ?? null);
        }
    }

    public function test_reimport_applies_bookable_plan_for_service_catalog(): void
    {
        [$owner, $company] = $this->actingOwner();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Services',
            'slug' => 'services',
            'catalog_mode' => CatalogMode::SERVICE,
            'vertical' => 'general_service',
            'version' => 1,
            'items' => [[
                'id' => 'SVC1',
                'title' => 'Old Massage',
                'price' => 1000,
                'metadata' => [],
            ]],
            'columns' => [],
            'source' => 'excel',
        ]);

        $file = $this->makeServiceSpreadsheet([
            ['SVC1', 'Massage', 'Updated', '1500', 'Spa', '', '60 min', 'Available', ''],
        ]);

        $preview = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('catalogs.reimport-excel', $catalog->id), [
                'file' => $file,
                'preview_only' => 1,
            ]);

        $preview->assertOk();
        $preview->assertJsonStructure(['bookable_plan' => ['rows']]);

        $file = $this->makeServiceSpreadsheet([
            ['SVC1', 'Massage', 'Updated', '1500', 'Spa', '', '60 min', 'Available', ''],
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->post(route('catalogs.reimport-excel', $catalog->id), [
                'file' => $file,
                'booking_defaults' => json_encode([
                    'default_duration_minutes' => 60,
                    'timezone' => 'UTC',
                    'staff_ids' => [],
                ]),
                'booking_decisions' => json_encode([
                    ['item_id' => 'SVC1', 'action' => 'create', 'name' => 'Massage', 'duration_minutes' => 60],
                ]),
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('booking_stats.created', 1);

        $catalog->refresh();
        $item = collect($catalog->items)->firstWhere('id', 'SVC1');
        $this->assertNotEmpty($item['metadata']['booking_source_id'] ?? null);
        $this->assertDatabaseHas('rem_res_sources', [
            'company_id' => $company->id,
            'name' => 'Massage',
        ]);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function makeServiceSpreadsheet(array $rows): UploadedFile
    {
        $headers = [
            'Item ID',
            'Title',
            'Description',
            'Price',
            'Category',
            'Image URL',
            'Duration',
            'Availability',
            'Bookable service',
        ];

        return $this->makeSpreadsheet($headers, $rows, 'services.xlsx');
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function makeListingSpreadsheet(array $rows): UploadedFile
    {
        $headers = [
            'Item ID',
            'Title',
            'Description',
            'Price',
            'Category',
            'Image URL',
            'Location',
            'Latitude',
            'Longitude',
            'Status',
            'Bookable service',
        ];

        return $this->makeSpreadsheet($headers, $rows, 'listings.xlsx');
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function makeJobsSpreadsheet(array $rows): UploadedFile
    {
        $headers = [
            'Item ID',
            'Title',
            'Description',
            'Price',
            'Category',
            'Image URL',
            'Image URLs',
            'Tags',
            'Company',
            'Employment type',
            'Salary',
            'Experience',
            'Education',
            'Skills',
            'Location',
            'Application deadline',
            'Benefits',
            'Apply to email',
            'Status',
        ];

        return $this->makeSpreadsheet($headers, $rows, 'jobs.xlsx');
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function makeCommerceSpreadsheet(array $rows): UploadedFile
    {
        $headers = [
            'Item ID',
            'Title',
            'Description',
            'Price',
            'Category',
            'Image URL',
            'Stock Status',
            'Variants',
            'Tags',
        ];

        return $this->makeSpreadsheet($headers, $rows, 'commerce.xlsx');
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function makeSpreadsheet(array $headers, array $rows, string $filename): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(array_merge([$headers], $rows));

        $path = tempnam(sys_get_temp_dir(), 'catalog_import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function actingOwner(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        return [$owner, $company];
    }
}
