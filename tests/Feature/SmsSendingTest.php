<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\Campaign\Channels\CampaignChannelRegistry;
use App\Services\Campaign\Channels\SmsCampaignBatchSender;
use App\Services\Telephony\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SmsSendingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        config([
            'settings.enable_credits' => false,
            'hostpinnacle.enabled' => false,
            'hostpinnacle.base_url' => 'https://smsportal.hostpinnacle.co.ke',
            'hostpinnacle.bulk_min_batch' => 2,
            'hostpinnacle.require_approved_sender_id' => true,
        ]);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
        $this->company = Company::factory()->create(['user_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);

        config(['hostpinnacle.enabled' => true]);

        $this->company->setMultipleConfig([
            'HOSTPINNACLE_USER_ID' => 'tenant1',
            'HOSTPINNACLE_API_KEY' => 'test-api-key',
            'HOSTPINNACLE_SENDER_ID' => 'CONVOCON',
            'HOSTPINNACLE_SENDER_STATUS' => 'approved',
        ]);
    }

    public function test_sms_sender_sends_via_host_pinnacle_quick_api(): void
    {
        Http::fake([
            'smsportal.hostpinnacle.co.ke/SMSApi/account/readstatus' => Http::response([
                'response' => [
                    'status' => 'success',
                    'account' => ['smsBalance' => '100'],
                ],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/send' => Http::response([
                'status' => 'success',
                'transactionId' => 'txn-123',
                'statusCode' => '200',
                'reason' => 'success',
            ], 200),
        ]);

        $result = app(SmsSender::class)->send($this->company, '+254712345678', 'Hello there');

        $this->assertTrue($result->success);
        $this->assertSame('txn-123', $result->providerMessageId);
    }

    public function test_chat_controller_uses_unified_sms_sender(): void
    {
        Http::fake([
            'smsportal.hostpinnacle.co.ke/*' => Http::sequence()
                ->push([
                    'response' => [
                        'status' => 'success',
                        'account' => ['smsBalance' => '50'],
                    ],
                ], 200)
                ->push([
                    'status' => 'success',
                    'transactionId' => 'txn-chat-1',
                    'statusCode' => '200',
                ], 200),
        ]);

        session(['company_id' => $this->company->id]);
        $this->actingAs($this->owner);

        $response = $this->post('/api/smswpbox/send', [
            'message' => 'Hi',
            'phone' => '0712345678',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'transaction_id' => 'txn-chat-1',
            ]);
    }

    public function test_campaign_registry_stores_provider_message_id(): void
    {
        Http::fake([
            'smsportal.hostpinnacle.co.ke/*' => Http::sequence()
                ->push([
                    'response' => [
                        'status' => 'success',
                        'account' => ['smsBalance' => '50'],
                    ],
                ], 200)
                ->push([
                    'status' => 'success',
                    'transactionId' => 'txn-campaign-1',
                    'statusCode' => '200',
                ], 200),
        ]);

        $contact = Contact::create([
            'name' => 'Jane',
            'phone' => '+254712345678',
            'company_id' => $this->company->id,
            'subscribed' => 1,
        ]);

        $campaign = Campaign::create([
            'name' => 'SMS Test',
            'company_id' => $this->company->id,
            'channel' => Campaign::CHANNEL_SMS,
            'status' => Campaign::STATUS_SENDING,
            'is_active' => true,
        ]);

        $message = Message::create([
            'contact_id' => $contact->id,
            'company_id' => $this->company->id,
            'campaign_id' => $campaign->id,
            'value' => 'Campaign hello',
            'status' => Message::STATUS_PENDING,
            'components' => '[]',
            'buttons' => '[]',
        ]);

        $sent = app(CampaignChannelRegistry::class)->send(Campaign::CHANNEL_SMS, $message, $this->company);

        $this->assertTrue($sent);
        $this->assertSame(Message::STATUS_SENT, $message->fresh()->status);
        $this->assertSame('txn-campaign-1', $message->fresh()->provider_message_id);
    }

    public function test_campaign_batch_sender_uses_bulk_upload_for_identical_messages(): void
    {
        Http::fake([
            'smsportal.hostpinnacle.co.ke/SMSApi/account/readstatus' => Http::response([
                'response' => [
                    'status' => 'success',
                    'account' => ['smsBalance' => '100'],
                ],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/send' => Http::response([
                'status' => 'success',
                'transactionId' => 'txn-bulk-1',
                'statusCode' => '200',
            ], 200),
        ]);

        $campaign = Campaign::create([
            'name' => 'Bulk SMS',
            'company_id' => $this->company->id,
            'channel' => Campaign::CHANNEL_SMS,
            'status' => Campaign::STATUS_SENDING,
            'is_active' => true,
        ]);

        $messages = collect([
            $this->makePendingMessage($campaign, 'Alice', '+254711111111', 'Same body'),
            $this->makePendingMessage($campaign, 'Bob', '+254722222222', 'Same body'),
        ]);

        $sent = app(SmsCampaignBatchSender::class)->dispatch($messages);

        $this->assertSame(2, $sent);
        $this->assertSame('txn-bulk-1', $messages[0]->fresh()->provider_message_id);
        $this->assertSame('txn-bulk-1', $messages[1]->fresh()->provider_message_id);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/SMSApi/send')
                && str_contains($request->body(), 'bulkupload');
        });
    }

    public function test_host_pinnacle_dlr_webhook_returns_ok(): void
    {
        $this->post('/webhook/sms/convoconnect/dlr', [
            'transactionId' => 'txn-bulk-1',
            'status' => 'DELIVERED',
        ])->assertOk()->assertJson(['status' => 'ok']);
    }

    private function makePendingMessage(Campaign $campaign, string $name, string $phone, string $body): Message
    {
        $contact = Contact::create([
            'name' => $name,
            'phone' => $phone,
            'company_id' => $this->company->id,
            'subscribed' => 1,
        ]);

        return Message::create([
            'contact_id' => $contact->id,
            'company_id' => $this->company->id,
            'campaign_id' => $campaign->id,
            'value' => $body,
            'status' => Message::STATUS_PENDING,
            'components' => '[]',
            'buttons' => '[]',
        ]);
    }
}
