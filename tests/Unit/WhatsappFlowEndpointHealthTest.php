<?php

namespace Tests\Unit;

use App\Services\WhatsappMetaFlowService;
use Illuminate\Support\Facades\Http;
use Modules\Wpbox\Http\Controllers\FlowsWebhookController;
use ReflectionMethod;
use Tests\TestCase;

class WhatsappFlowEndpointHealthTest extends TestCase
{
    public function test_ping_response_includes_version_and_active_status(): void
    {
        $controller = app(FlowsWebhookController::class);
        $method = new ReflectionMethod($controller, 'getNextScreen');
        $method->setAccessible(true);

        $response = $method->invoke($controller, [
            'version' => '3.0',
            'action' => 'ping',
        ]);

        $this->assertSame('3.0', $response['version']);
        $this->assertSame('active', $response['data']['status']);
    }

    public function test_health_check_blocks_when_flow_entity_has_endpoint_error(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'health_status' => [
                    'can_send_message' => 'BLOCKED',
                    'entities' => [[
                        'entity_type' => 'FLOW',
                        'id' => '123',
                        'can_send_message' => 'BLOCKED',
                        'errors' => [[
                            'error_code' => 131000,
                            'error_description' => 'endpoint_available: You need to verify that the endpoint is available and that you\'ve implemented a health check before publishing.',
                        ]],
                    ]],
                ],
                'validation_errors' => [],
                'endpoint_uri' => 'https://example.test/webhook',
                'status' => 'DRAFT',
            ]),
        ]);

        $service = app(WhatsappMetaFlowService::class);
        $method = new ReflectionMethod($service, 'checkFlowEndpointHealth');
        $method->setAccessible(true);

        $result = $method->invoke($service, '1670674954166204', 'test-token');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['retryable']);
        $this->assertStringContainsString('endpoint_available', $result['message']);
    }

    public function test_health_check_passes_when_flow_entity_available(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'health_status' => [
                    'can_send_message' => 'AVAILABLE',
                    'entities' => [[
                        'entity_type' => 'FLOW',
                        'id' => '123',
                        'can_send_message' => 'AVAILABLE',
                    ]],
                ],
                'validation_errors' => [],
                'status' => 'DRAFT',
            ]),
        ]);

        $service = app(WhatsappMetaFlowService::class);
        $method = new ReflectionMethod($service, 'checkFlowEndpointHealth');
        $method->setAccessible(true);

        $result = $method->invoke($service, '1670674954166204', 'test-token');

        $this->assertTrue($result['success']);
    }

    public function test_encrypt_decrypt_round_trip_for_ping_payload(): void
    {
        $controller = app(FlowsWebhookController::class);
        $encrypt = new ReflectionMethod($controller, 'encryptResponse');
        $encrypt->setAccessible(true);

        $aesKey = random_bytes(16);
        $iv = random_bytes(16);
        $payload = [
            'version' => '3.0',
            'data' => ['status' => 'active'],
        ];

        $encrypted = $encrypt->invoke($controller, $payload, $aesKey, $iv);
        $this->assertNotEmpty($encrypted);

        $raw = base64_decode($encrypted);
        $tag = substr($raw, -16);
        $ciphertext = substr($raw, 0, -16);
        $flippedIv = ~$iv;

        $decrypted = openssl_decrypt($ciphertext, 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $flippedIv, $tag);
        $this->assertNotFalse($decrypted);
        $this->assertSame($payload, json_decode($decrypted, true));
    }
}
