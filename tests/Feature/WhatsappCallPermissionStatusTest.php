<?php

namespace Tests\Feature;

use App\Enums\MessagingChannelType;
use App\Http\Middleware\EnsurePlanPlugin;
use App\Models\Company;
use App\Models\Messaging\ChannelIdentity;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsappCallPermissionStatusTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
        $this->company = Company::factory()->create(['user_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);

        $this->company->setConfig('whatsapp_permanent_access_token', 'wa-token');
        $this->company->setConfig('whatsapp_phone_number_id', '1302160952973842');

        $this->withoutMiddleware([
            EnsurePlanPlugin::class,
            \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        ]);
    }

    public function test_permission_status_skips_graph_for_contacts_without_phone(): void
    {
        Http::fake();

        $contact = $this->makeContact(['phone' => '']);

        $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->getJson(route('whatsappcall.bic.permission_status', ['contact_id' => $contact->id]))
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error_message', 'Not a WhatsApp contact');

        Http::assertNothingSent();
    }

    public function test_permission_status_skips_graph_for_messenger_contacts(): void
    {
        Http::fake();

        $contact = $this->makeContact(['phone' => '28802036889383120']);

        ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'contact_id' => $contact->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_id' => '28802036889383120',
        ]);

        $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->getJson(route('whatsappcall.bic.permission_status', ['contact_id' => $contact->id]))
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error_message', 'Not a WhatsApp contact');

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeContact(array $attributes = []): Contact
    {
        return Contact::withoutGlobalScope(CompanyScope::class)->create(array_merge([
            'name' => 'Customer',
            'phone' => '254700000000',
            'company_id' => $this->company->id,
            'has_chat' => true,
        ], $attributes));
    }
}
