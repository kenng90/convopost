<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Wpbox\Http\Middleware\CheckAPIPlan;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_login_responses_include_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_api_contact_update_cannot_change_company_id(): void
    {
        $this->withoutMiddleware(CheckAPIPlan::class);

        $ownerA = User::factory()->create();
        $ownerA->assignRole('owner');
        $companyA = Company::factory()->create(['user_id' => $ownerA->id]);
        $ownerA->update(['company_id' => $companyA->id]);

        $ownerB = User::factory()->create();
        $ownerB->assignRole('owner');
        $companyB = Company::factory()->create(['user_id' => $ownerB->id]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $companyA->id,
            'name' => 'Jane Doe',
            'phone' => '+254700000010',
            'email' => 'jane@example.com',
        ]);

        $token = $ownerA->createToken('security-test')->plainTextToken;

        $response = $this->postJson('/api/wpbox/updateContact', [
            'token' => $token,
            'id' => $contact->id,
            'name' => 'Jane Updated',
            'company_id' => $companyB->id,
            'email' => 'jane-updated@example.com',
        ]);

        $response->assertSuccessful();
        $contact->refresh();
        $this->assertSame('Jane Updated', $contact->name);
        $this->assertSame($companyA->id, (int) $contact->company_id);
    }

    public function test_api_contact_update_cannot_access_other_company_contact(): void
    {
        $this->withoutMiddleware(CheckAPIPlan::class);

        $ownerA = User::factory()->create();
        $ownerA->assignRole('owner');
        $companyA = Company::factory()->create(['user_id' => $ownerA->id]);
        $ownerA->update(['company_id' => $companyA->id]);

        $ownerB = User::factory()->create();
        $ownerB->assignRole('owner');
        $companyB = Company::factory()->create(['user_id' => $ownerB->id]);

        $foreignContact = Contact::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'name' => 'Foreign',
            'phone' => '+254700000011',
        ]);

        $token = $ownerA->createToken('security-test')->plainTextToken;

        $this->postJson('/api/wpbox/updateContact', [
            'token' => $token,
            'id' => $foreignContact->id,
            'name' => 'Stolen',
        ])->assertNotFound();

        $this->assertSame('Foreign', $foreignContact->fresh()->name);
    }

    public function test_api_login_is_rate_limited(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-pass'),
        ]);
        $user->assignRole('owner');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v2/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertOk();
        }

        $this->postJson('/api/v2/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_api_login_does_not_return_password(): void
    {
        $user = User::factory()->create();
        $user->assignRole('owner');

        $response = $this->postJson('/api/v2/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonMissingPath('password');
        $this->assertArrayNotHasKey('password', $response->json());
    }

    public function test_media_url_rejects_private_addresses(): void
    {
        $this->withoutMiddleware(CheckAPIPlan::class);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'SSRF Target',
            'phone' => '+254700000012',
        ]);

        $token = $owner->createToken('security-test')->plainTextToken;

        $response = $this->postJson('/api/wpbox/sendmessage', [
            'token' => $token,
            'phone' => '+254700000012',
            'media_url' => 'http://127.0.0.1/secret.png',
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('message', 'Media URL is not allowed');
    }
}
