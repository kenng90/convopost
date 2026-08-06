<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Nodes\Edge;
use Modules\Flowmaker\Models\Nodes\End;
use Modules\Flowmaker\Models\Nodes\Message as MessageNode;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FlowMessageNodeCreditBillingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id,
            'subdomain' => 'flow-msg-credits',
        ]);
        $this->owner->update(['company_id' => $this->company->id]);

        $this->company->setConfig('whatsapp_permanent_access_token', 'test-token-abcdef');
        $this->company->setConfig('whatsapp_phone_number_id', 'phone-123');

        session(['company_id' => $this->company->id]);
        config(['settings.enable_credits' => true]);

        $this->contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Kenneth',
            'phone' => '254716217015',
            'company_id' => $this->company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
            'last_client_reply_at' => now(),
        ]);
    }

    public function test_bot_message_inside_service_window_sends_without_credits(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.test.success']],
            ], 200),
        ]);

        $node = $this->makeMessageNode('Thanks for booking.');

        $result = $node->process('None', (object) ['contact_id' => $this->contact->id]);

        $this->assertTrue($result['success']);

        $outbound = Message::query()
            ->where('contact_id', $this->contact->id)
            ->where('is_message_by_contact', false)
            ->latest('id')
            ->first();

        $this->assertNotNull($outbound);
        $this->assertNotSame(2, (int) $outbound->status);
        $this->assertSame('wamid.test.success', $outbound->fb_message_id);
        $this->assertSame(0, $this->owner->fresh()->getTotalRemainingCredits());
    }

    public function test_bot_message_outside_service_window_fails_without_credits(): void
    {
        $this->contact->update(['last_client_reply_at' => now()->subHours(30)]);

        $node = $this->makeMessageNode('Thanks for booking.');

        $result = $node->process('None', (object) ['contact_id' => $this->contact->id]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('credits', strtolower($result['error']));

        $outbound = Message::query()
            ->where('contact_id', $this->contact->id)
            ->where('is_message_by_contact', false)
            ->latest('id')
            ->first();

        $this->assertNotNull($outbound);
        $this->assertSame(2, (int) $outbound->status);
        $this->assertNull($outbound->fb_message_id);
    }

    public function test_failed_message_node_does_not_continue_to_end_node(): void
    {
        $this->contact->update(['last_client_reply_at' => now()->subHours(30)]);
        $this->contact->setContactState(73, 'spa_booking_notes', 'None');

        $endNode = new End([
            'id' => 'end-1',
            'type' => 'end',
            'data' => [],
        ], []);
        $endNode->flow_id = 73;

        $messageNode = $this->makeMessageNode('Thanks for booking.');
        $messageNode->flow_id = 73;

        $edge = new Edge([
            'id' => 'e1',
            'source' => 'message-2',
            'target' => 'end-1',
        ]);
        $edge->setSource($messageNode);
        $edge->setTarget($endNode);
        $messageNode->addOutgoingEdge($edge);

        $result = $messageNode->process('None', (object) ['contact_id' => $this->contact->id]);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'None',
            $this->contact->fresh()->getContactStateValue(73, 'spa_booking_notes'),
        );
    }

    private function makeMessageNode(string $text): MessageNode
    {
        $node = new MessageNode([
            'id' => 'message-2',
            'type' => 'message',
            'data' => [
                'settings' => [
                    'message' => $text,
                ],
            ],
        ], []);
        $node->flow_id = 73;

        return $node;
    }
}
