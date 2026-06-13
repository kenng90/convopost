<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Contacts\Jobs\ProcessContactImportJob;
use Modules\Contacts\Models\ContactImport;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactImportQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_import_request_queues_background_job(): void
    {
        Queue::fake();
        Storage::fake('local');

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        $csv = UploadedFile::fake()->createWithContent(
            'contacts.csv',
            "phone,name\n254798000010,Jane Miller\n"
        );

        $response = $this->actingAs($owner)->post(route('contacts.import.store'), [
            'csv' => $csv,
        ]);

        $response->assertRedirect();

        Queue::assertPushed(ProcessContactImportJob::class);

        $this->assertDatabaseHas('contact_imports', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'status' => ContactImport::STATUS_PENDING,
            'original_filename' => 'contacts.csv',
        ]);
    }

    public function test_process_contact_import_job_imports_contacts(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        session(['company_id' => $company->id]);

        $filePath = 'contact-imports/test.csv';
        Storage::disk('local')->put($filePath, "phone,name\n254798000011,Alice Jones\n");

        $contactImport = ContactImport::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'file_path' => $filePath,
            'disk' => 'local',
            'original_filename' => 'test.csv',
            'status' => ContactImport::STATUS_PENDING,
        ]);

        (new ProcessContactImportJob($contactImport))->handle();

        $contactImport->refresh();

        $this->assertSame(ContactImport::STATUS_COMPLETED, $contactImport->status);
        $this->assertSame(1, $contactImport->created_count);
        $this->assertDatabaseHas('contacts', [
            'company_id' => $company->id,
            'phone' => '+254798000011',
            'name' => 'Alice Jones',
        ]);
    }

    public function test_import_status_endpoint_returns_progress_payload(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        $contactImport = ContactImport::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'file_path' => 'contact-imports/test.csv',
            'disk' => 'local',
            'original_filename' => 'test.csv',
            'status' => ContactImport::STATUS_PROCESSING,
            'total_rows' => 100,
            'processed_rows' => 25,
            'created_count' => 20,
            'updated_count' => 5,
        ]);

        $response = $this->actingAs($owner)->getJson(route('contacts.import.status', $contactImport));

        $response->assertOk()
            ->assertJson([
                'status' => ContactImport::STATUS_PROCESSING,
                'total_rows' => 100,
                'processed_rows' => 25,
                'progress_percent' => 25,
                'created_count' => 20,
                'updated_count' => 5,
                'is_finished' => false,
            ]);
    }

    public function test_import_status_endpoint_finalizes_stuck_processing_import(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        $contactImport = ContactImport::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'file_path' => 'contact-imports/test.csv',
            'disk' => 'local',
            'original_filename' => 'test.csv',
            'status' => ContactImport::STATUS_PROCESSING,
            'total_rows' => 10,
            'processed_rows' => 10,
        ]);

        $response = $this->actingAs($owner)->getJson(route('contacts.import.status', $contactImport));

        $response->assertOk()->assertJson([
            'status' => ContactImport::STATUS_COMPLETED,
            'is_finished' => true,
        ]);
    }

    public function test_active_import_endpoint_lists_running_imports(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        ContactImport::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'file_path' => 'contact-imports/test.csv',
            'disk' => 'local',
            'original_filename' => 'running.csv',
            'status' => ContactImport::STATUS_PROCESSING,
        ]);

        $response = $this->actingAs($owner)->getJson(route('contacts.import.active'));

        $response->assertOk()
            ->assertJsonCount(1, 'imports')
            ->assertJsonPath('imports.0.original_filename', 'running.csv');
    }

    public function test_second_import_is_redirected_when_one_is_already_active(): void
    {
        Queue::fake();
        Storage::fake('local');

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        $activeImport = ContactImport::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'file_path' => 'contact-imports/active.csv',
            'disk' => 'local',
            'original_filename' => 'active.csv',
            'status' => ContactImport::STATUS_PROCESSING,
        ]);

        $csv = UploadedFile::fake()->createWithContent(
            'contacts.csv',
            "phone,name\n254798000012,Bob\n"
        );

        $response = $this->actingAs($owner)->post(route('contacts.import.store'), [
            'csv' => $csv,
        ]);

        $response->assertRedirect(route('contacts.import.show', $activeImport));
        Queue::assertNotPushed(ProcessContactImportJob::class);
    }
}
