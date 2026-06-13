<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contacts\Imports\ContactsImport;
use Modules\Contacts\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactsImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_import_uses_unknown_as_name_when_name_is_missing(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        session(['company_id' => $company->id]);

        $import = new ContactsImport;
        $import->model([
            'phone' => '254798000000',
            'name' => null,
        ]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('phone', '+254798000000')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('Unknown', $contact->name);
    }

    public function test_import_preserves_existing_name_when_update_row_has_empty_name(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        session(['company_id' => $company->id]);

        Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'Jane Doe',
            'phone' => '+254798000001',
            'company_id' => $company->id,
        ]);

        $import = new ContactsImport;
        $import->model([
            'phone' => '254798000001',
            'name' => null,
        ]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('phone', '+254798000001')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('Jane Doe', $contact->name);
    }

    public function test_import_skips_rows_without_phone(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        session(['company_id' => $company->id]);

        $import = new ContactsImport;
        $result = $import->model([
            'phone' => null,
            'name' => 'No Phone Contact',
        ]);

        $this->assertNull($result);
        $this->assertSame(0, Contact::withoutGlobalScope(CompanyScope::class)->count());
    }

    public function test_import_uses_first_non_empty_name_when_duplicate_name_columns_exist(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        session(['company_id' => $company->id]);

        $import = new ContactsImport;
        $import->model([
            'name' => ['Jane Miller', null],
            'phone' => '254798000002',
        ]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('phone', '+254798000002')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('Jane Miller', $contact->name);
    }

    public function test_import_normalizes_scientific_notation_phone_numbers(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        session(['company_id' => $company->id]);

        $import = new ContactsImport;
        $import->model([
            'name' => 'Alice Jones',
            'phone' => '2.54798E+11',
        ]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('+254798000000', $contact->phone);
    }
}
