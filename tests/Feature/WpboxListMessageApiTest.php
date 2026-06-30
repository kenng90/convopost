<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wpbox\Http\Middleware\CheckAPIPlan;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WpboxListMessageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_list_message_allows_empty_footer_text(): void
    {
        $this->withoutMiddleware(CheckAPIPlan::class);
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        session(['company_id' => $company->id]);

        $token = $owner->createToken('list-message-test')->plainTextToken;

        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Jane Doe',
            'phone' => '254712345678',
            'company_id' => $company->id,
            'has_chat' => true,
        ]);

        $response = $this->postJson('/api/wpbox/sendlistmessage', [
            'token' => $token,
            'phone' => $contact->phone,
            'message' => 'How long should this appointment be?',
            'header' => 'Duration',
            'footer' => null,
            'action' => [
                'button' => 'Choose option',
                'sections' => [[
                    'title' => 'Duration',
                    'rows' => [[
                        'id' => 'ba-duration-MzA_idbook-1_flow1',
                        'title' => '30 minutes',
                        'description' => '',
                    ]],
                ]],
            ],
        ]);

        $response->assertSuccessful();

        $message = Message::withoutGlobalScopes()
            ->where('contact_id', $contact->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($message);
        $this->assertSame('', $message->footer_text);
        $this->assertSame('Duration', $message->header_text);
    }
}
