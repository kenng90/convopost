<?php

namespace Tests\Feature;

use App\Enums\MessagingChannelType;
use App\Models\Company;
use App\Models\Messaging\ChannelConnection;
use App\Models\Messaging\ChannelIdentity;
use App\Models\Messaging\Conversation;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Flowmaker\FlowHealthValidator;
use App\Services\Flowmaker\FlowOutboundService;
use App\Services\Messaging\DTO\MessageContent;
use App\Services\Messaging\MetaMessagingParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Flowmaker\Jobs\ProcessFlowMessage;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Models\Nodes\Edge;
use Modules\Flowmaker\Models\Nodes\End;
use Modules\Flowmaker\Models\Nodes\Message as MessageNode;
use Modules\Wpbox\Events\ContactReplies;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FlowmakerOmnichannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.enable_credits' => false]);
    }

    public function test_instagram_contact_is_created_with_ai_bot_enabled(): void
    {
        Event::fake([ContactReplies::class]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $webhookToken = 'ig-bot-enable-token';
        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-1',
            'display_name' => 'Instagram',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-1',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $payload = [
            'object' => 'instagram',
            'entry' => [[
                'id' => 'page-1',
                'time' => 1710000000000,
                'messaging' => [[
                    'sender' => ['id' => 'ig-user-1'],
                    'recipient' => ['id' => 'page-1'],
                    'timestamp' => 1710000000000,
                    'message' => [
                        'mid' => 'mid.BOT_ENABLE_001',
                        'text' => 'hi',
                    ],
                ]],
            ]],
        ];

        $this->postJson('/webhook/messaging/instagram/receive/'.$webhookToken, $payload)
            ->assertOk();

        $contact = Contact::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $this->assertNotNull($contact);
        $this->assertTrue((bool) $contact->enabled_ai_bot);
    }

    public function test_instagram_inbound_dispatches_flowmaker_job(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'IG Omni Flow',
            'is_active' => true,
            'flow_data' => json_encode([
                'nodes' => [
                    [
                        'id' => 'keyword_trigger-1',
                        'type' => 'keyword_trigger',
                        'data' => [
                            'settings' => [
                                'keywords' => [
                                    ['id' => 'kw1', 'value' => 'hello', 'matchType' => 'contains'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'message-1',
                        'type' => 'message',
                        'data' => ['settings' => ['message' => 'Welcome']],
                    ],
                ],
                'edges' => [
                    [
                        'id' => 'e1',
                        'source' => 'keyword_trigger-1',
                        'target' => 'message-1',
                        'sourceHandle' => 'keyword-kw1',
                    ],
                ],
            ]),
        ]);

        $webhookToken = 'ig-flow-dispatch-token';
        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-2',
            'display_name' => 'Instagram',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-2',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $payload = [
            'object' => 'instagram',
            'entry' => [[
                'id' => 'page-2',
                'time' => 1710000000000,
                'messaging' => [[
                    'sender' => ['id' => 'ig-user-2'],
                    'recipient' => ['id' => 'page-2'],
                    'timestamp' => 1710000000000,
                    'message' => [
                        'mid' => 'mid.FLOW_DISPATCH_001',
                        'text' => 'hello there',
                    ],
                ]],
            ]],
        ];

        $this->postJson('/webhook/messaging/instagram/receive/'.$webhookToken, $payload)
            ->assertOk();

        Queue::assertPushed(ProcessFlowMessage::class);
    }

    public function test_parser_maps_quick_reply_payload_to_extra(): void
    {
        $parser = app(MetaMessagingParser::class);

        $request = Request::create('/webhook', 'POST', [
            'entry' => [[
                'messaging' => [[
                    'sender' => ['id' => 'ig-user-3'],
                    'recipient' => ['id' => 'page-3'],
                    'timestamp' => 1710000000000,
                    'message' => [
                        'mid' => 'mid.QR_001',
                        'text' => 'Yes',
                        'quick_reply' => [
                            'payload' => 'button-1_id42_flow7',
                        ],
                    ],
                ]],
            ]],
        ]);

        $batch = $parser->parsePageMessaging($request, MessagingChannelType::Instagram);

        $this->assertCount(1, $batch->messages);
        $this->assertSame('button-1_id42_flow7', $batch->messages[0]->extra);
        $this->assertSame('Yes', $batch->messages[0]->content->body);
    }

    public function test_parser_maps_postback_payload_to_extra(): void
    {
        $parser = app(MetaMessagingParser::class);

        $request = Request::create('/webhook', 'POST', [
            'entry' => [[
                'messaging' => [[
                    'sender' => ['id' => 'ms-user-1'],
                    'recipient' => ['id' => 'page-4'],
                    'timestamp' => 1710000000000,
                    'postback' => [
                        'title' => 'Option A',
                        'payload' => 'section1-row1_id10_flow3',
                        'mid' => 'mid.POSTBACK_001',
                    ],
                ]],
            ]],
        ]);

        $batch = $parser->parsePageMessaging($request, MessagingChannelType::Messenger);

        $this->assertCount(1, $batch->messages);
        $this->assertSame('section1-row1_id10_flow3', $batch->messages[0]->extra);
        $this->assertSame('Option A', $batch->messages[0]->content->body);
    }

    public function test_instagram_webhook_persists_quick_reply_extra(): void
    {
        Event::fake([ContactReplies::class]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $webhookToken = 'ig-qr-extra-token';
        ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-5',
            'display_name' => 'Instagram',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-5',
            ],
            'webhook_token' => $webhookToken,
        ]);

        $payload = [
            'object' => 'instagram',
            'entry' => [[
                'id' => 'page-5',
                'messaging' => [[
                    'sender' => ['id' => 'ig-user-5'],
                    'recipient' => ['id' => 'page-5'],
                    'timestamp' => 1710000000000,
                    'message' => [
                        'mid' => 'mid.QR_PERSIST_001',
                        'text' => 'Book',
                        'quick_reply' => [
                            'payload' => 'button-2_id99_flow5',
                        ],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/webhook/messaging/instagram/receive/'.$webhookToken, $payload)
            ->assertOk();

        $message = Message::withoutGlobalScopes()->where('fb_message_id', 'mid.QR_PERSIST_001')->first();
        $this->assertNotNull($message);
        $this->assertSame('button-2_id99_flow5', $message->extra);
    }

    public function test_flow_outbound_sends_meta_quick_replies(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['message_id' => 'mid.OUT_QR_001'], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $contact = Contact::withoutGlobalScope(CompanyScope::class)->create([
            'name' => 'IG User',
            'phone' => '',
            'company_id' => $company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $connection = ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_account_id' => 'page-6',
            'display_name' => 'Instagram',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-6',
            ],
        ]);

        \App\Models\Messaging\Conversation::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel_connection_id' => $connection->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_participant_id' => 'ig-user-6',
            'last_client_reply_at' => now(),
        ]);

        ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel' => MessagingChannelType::Instagram->value,
            'external_id' => 'ig-user-6',
        ]);

        $sent = app(FlowOutboundService::class)->sendChoices(
            $contact,
            'Pick one',
            [
                ['id' => 'button-1_id1_flow1', 'title' => 'Yes'],
                ['id' => 'button-2_id1_flow1', 'title' => 'No'],
            ],
        );

        $this->assertNotNull($sent);
        $this->assertSame(2, (int) $sent->status);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['message']['quick_replies'])
                && count($data['message']['quick_replies']) === 2
                && $data['message']['quick_replies'][0]['payload'] === 'button-1_id1_flow1';
        });
    }

    public function test_health_validator_warns_on_whatsapp_only_nodes(): void
    {
        $result = (new FlowHealthValidator)->validate([
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'data' => ['settings' => ['keywords' => []]],
                ],
                [
                    'id' => 'template-1',
                    'type' => 'template',
                    'data' => [],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'data' => [],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'keyword_trigger-1', 'target' => 'template-1', 'sourceHandle' => 'keyword-x'],
                ['id' => 'e2', 'source' => 'template-1', 'target' => 'end-1'],
            ],
            'supported_channels' => ['whatsapp', 'instagram', 'messenger'],
        ]);

        $this->assertNotEmpty($result['channel_compatibility']['whatsapp_only_nodes']);
        $this->assertTrue(collect($result['warnings'])->contains(
            fn ($warning) => str_contains($warning, 'WhatsApp-only')
        ));
    }

    public function test_message_content_supports_quick_replies(): void
    {
        $content = MessageContent::textWithQuickReplies('Hi', [
            ['content_type' => 'text', 'title' => 'A', 'payload' => 'a'],
        ]);

        $this->assertSame('TEXT', $content->type);
        $this->assertCount(1, $content->quickReplies);
    }

    public function test_channel_compatibility_lists_whatsapp_only_types(): void
    {
        $this->assertTrue((new FlowChannelCompatibility)->isWhatsappOnlyType('whatsapp_flow'));
        $this->assertFalse((new FlowChannelCompatibility)->isWhatsappOnlyType('message'));
    }

    public function test_messenger_successful_send_continues_flow_when_status_is_sent(): void
    {
        config(['settings.enable_credits' => false]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['message_id' => 'mid.MSG_OK'], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Messenger User',
            'phone' => '',
            'company_id' => $company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $connection = ChannelConnection::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_account_id' => 'page-ms',
            'display_name' => 'Messenger',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'page-token',
                'page_id' => 'page-ms',
            ],
        ]);

        Conversation::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel_connection_id' => $connection->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_participant_id' => 'ms-user-1',
            'last_client_reply_at' => now(),
        ]);

        ChannelIdentity::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'channel' => MessagingChannelType::Messenger->value,
            'external_id' => 'ms-user-1',
        ]);

        $endNode = new End([
            'id' => 'end-1',
            'type' => 'end',
            'data' => [],
        ], []);
        $endNode->flow_id = 99;

        $messageNode = new MessageNode([
            'id' => 'message-1',
            'type' => 'message',
            'data' => [
                'settings' => [
                    'message' => 'Welcome to our spa!',
                ],
            ],
        ], []);
        $messageNode->flow_id = 99;

        // Stub continuation by attaching a simple end edge that marks success path.
        $edge = new Edge([
            'id' => 'e1',
            'source' => 'message-1',
            'target' => 'end-1',
        ]);
        $edge->setSource($messageNode);
        $edge->setTarget($endNode);
        $messageNode->addOutgoingEdge($edge);

        $result = $messageNode->process('book', (object) ['contact_id' => $contact->id]);

        $this->assertTrue($result['success'], 'Messenger status=2 success must not abort the flow');

        $outbound = Message::withoutGlobalScopes()
            ->where('contact_id', $contact->id)
            ->where('is_message_by_contact', false)
            ->latest('id')
            ->first();

        $this->assertNotNull($outbound);
        $this->assertSame(2, (int) $outbound->status);
        $this->assertSame('mid.MSG_OK', $outbound->fb_message_id);
        $this->assertTrue(blank($outbound->error));
    }
}
