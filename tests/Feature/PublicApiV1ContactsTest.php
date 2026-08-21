<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wpbox\Models\Contact;
use Tests\Support\CreatesPublicApiUser;
use Tests\TestCase;

class PublicApiV1ContactsTest extends TestCase
{
    use CreatesPublicApiUser;
    use RefreshDatabase;

    public function test_contacts_are_cursor_paginated_with_hard_cap(): void
    {
        $this->createPublicApiOwner();

        foreach (range(1, 3) as $i) {
            Contact::withoutGlobalScopes()->create([
                'company_id' => $this->apiCompany->id,
                'name' => 'Contact '.$i,
                'phone' => '+25470000010'.$i,
            ]);
        }

        $response = $this->getJson('/api/v1/contacts?limit=2', $this->publicApiHeaders());

        $response->assertOk()
            ->assertJsonPath('meta.limit', 2)
            ->assertJsonPath('meta.has_more', true);
        $this->assertCount(2, $response->json('data'));
        $this->assertNotEmpty($response->json('meta.next_cursor'));

        $next = $this->getJson('/api/v1/contacts?limit=2&cursor='.$response->json('meta.next_cursor'), $this->publicApiHeaders());
        $next->assertOk()->assertJsonPath('meta.has_more', false);
        $this->assertCount(1, $next->json('data'));
    }

    public function test_legacy_get_contacts_is_paginated(): void
    {
        $this->createPublicApiOwner();

        Contact::withoutGlobalScopes()->create([
            'company_id' => $this->apiCompany->id,
            'name' => 'Jane',
            'phone' => '+254700000200',
        ]);

        $this->getJson('/api/wpbox/getContacts?limit=50', $this->publicApiHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.limit', 50)
            ->assertJsonPath('contacts.0.name', 'Jane');
    }

    public function test_contacts_can_be_created_and_filtered_by_phone(): void
    {
        $this->createPublicApiOwner();

        $created = $this->postJson('/api/v1/contacts', [
            'phone' => '+254700000300',
            'name' => 'Ada',
        ], $this->publicApiHeaders())->assertCreated();

        $this->getJson('/api/v1/contacts?'.http_build_query([
            'phone' => $created->json('data.phone'),
        ]), $this->publicApiHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ada');
    }
}
