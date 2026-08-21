<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesPublicApiUser;
use Tests\TestCase;

class PublicApiV1MessagesTest extends TestCase
{
    use CreatesPublicApiUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPublicApiOwner();

        config([
            'hostpinnacle.enabled' => true,
            'hostpinnacle.base_url' => 'https://smsportal.hostpinnacle.co.ke',
            'hostpinnacle.require_approved_sender_id' => true,
        ]);

        $this->apiCompany->setMultipleConfig([
            'HOSTPINNACLE_USER_ID' => 'tenant1',
            'HOSTPINNACLE_API_KEY' => 'test-api-key',
            'HOSTPINNACLE_SENDER_ID' => 'CONVOCON',
            'HOSTPINNACLE_SENDER_STATUS' => 'approved',
        ]);
    }

    public function test_sms_messages_use_the_public_messages_api(): void
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
                'transactionId' => 'txn-api-1',
                'statusCode' => '200',
                'reason' => 'success',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/messages', [
            'channel' => 'sms',
            'to' => '+254712345678',
            'body' => 'Hello SMS',
        ], $this->publicApiHeaders([
            'Idempotency-Key' => 'sms-1',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.channel', 'sms')
            ->assertJsonPath('data.provider_message_id', 'txn-api-1');

        $replay = $this->postJson('/api/v1/messages', [
            'channel' => 'sms',
            'to' => '+254712345678',
            'body' => 'Hello SMS',
        ], $this->publicApiHeaders([
            'Idempotency-Key' => 'sms-1',
        ]));

        $replay->assertCreated()->assertHeader('Idempotent-Replay', 'true');
    }

    public function test_idempotency_conflict_returns_409(): void
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
                'transactionId' => 'txn-api-2',
                'statusCode' => '200',
                'reason' => 'success',
            ], 200),
        ]);

        $this->postJson('/api/v1/messages', [
            'channel' => 'sms',
            'to' => '+254712345679',
            'body' => 'First',
        ], $this->publicApiHeaders([
            'Idempotency-Key' => 'sms-conflict',
        ]))->assertCreated();

        $this->postJson('/api/v1/messages', [
            'channel' => 'sms',
            'to' => '+254712345679',
            'body' => 'Second',
        ], $this->publicApiHeaders([
            'Idempotency-Key' => 'sms-conflict',
        ]))->assertStatus(409);
    }
}
