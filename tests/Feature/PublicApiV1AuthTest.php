<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPublicApiUser;
use Tests\TestCase;

class PublicApiV1AuthTest extends TestCase
{
    use CreatesPublicApiUser;
    use RefreshDatabase;

    public function test_missing_token_returns_401(): void
    {
        $this->createPublicApiOwner();

        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_token');
    }

    public function test_bearer_token_and_company_header_authenticate(): void
    {
        $this->createPublicApiOwner();

        $this->getJson('/api/v1/me', $this->publicApiHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.company.id', $this->apiCompany->id)
            ->assertHeader('X-RateLimit-Limit');
    }

    public function test_plan_without_api_access_returns_403(): void
    {
        $this->createPublicApiOwner(['inbox', 'contacts']);

        $this->getJson('/api/v1/me', $this->publicApiHeaders())
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'plan_forbidden');
    }

    public function test_x_company_id_must_be_accessible(): void
    {
        $this->createPublicApiOwner();
        $foreign = Company::factory()->create();

        $this->getJson('/api/v1/me', $this->publicApiHeaders([
            'X-Company-Id' => (string) $foreign->id,
        ]))->assertStatus(403);
    }

    public function test_legacy_sendmessage_accepts_bearer_without_body_token(): void
    {
        $this->createPublicApiOwner();

        $this->postJson('/api/wpbox/sendmessage', [
            'phone' => '254700000099',
            'media_url' => 'http://127.0.0.1/secret.png',
        ], $this->publicApiHeaders())
            ->assertSuccessful()
            ->assertJsonPath('message', 'Media URL is not allowed');
    }

    public function test_openapi_docs_are_public(): void
    {
        $this->get('/api/v1/docs')->assertOk();
        $spec = $this->getJson('/api/v1/openapi.json')
            ->assertOk()
            ->json();

        $this->assertSame('ConvoConnect Public API', $spec['info']['title']);
        $this->assertSame('Send a WhatsApp or SMS message', $spec['paths']['/messages']['post']['summary']);
        $this->get('/api/v1/openapi.yaml')->assertOk();
    }
}
