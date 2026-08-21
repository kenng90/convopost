<?php

namespace Tests\Feature;

use App\Models\WebhookEndpoint;
use App\Scopes\CompanyScope;
use App\Services\Security\SafeRemoteUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesPublicApiUser;
use Tests\TestCase;

class PublicApiV1EventsAndWebhooksTest extends TestCase
{
    use CreatesPublicApiUser;
    use RefreshDatabase;

    public function test_store_event_legacy_platform_token_is_gone(): void
    {
        config(['wpbox.campaign_dispatch_token' => 'old-global-token']);

        $this->postJson('/webhook/wpbox/store-event', [
            'company_id' => 1,
            'event_type' => 'order.created',
        ], [
            'X-Store-Event-Token' => 'old-global-token',
        ])->assertStatus(410);
    }

    public function test_v1_events_are_tenant_scoped_and_dispatch_signed_webhooks(): void
    {
        $this->createPublicApiOwner();

        $this->mock(SafeRemoteUrl::class, function ($mock) {
            $mock->shouldReceive('isPublicHttpUrl')->andReturn(true);
        });

        Http::fake([
            'https://example.com/*' => Http::response('ok', 200),
        ]);

        $created = $this->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/webhooks/convoconnect',
            'events' => ['order.created'],
        ], $this->publicApiHeaders());

        $created->assertCreated()->assertJsonPath('data.url', 'https://example.com/webhooks/convoconnect');
        $this->assertNotEmpty($created->json('data.secret'));

        $this->postJson('/api/v1/events', [
            'event' => 'order.created',
            'data' => ['order_id' => '1001'],
        ], $this->publicApiHeaders())->assertStatus(202);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhooks/convoconnect'
                && str_starts_with((string) $request->header('X-ConvoConnect-Signature')[0], 't=');
        });

        $this->assertTrue(
            WebhookEndpoint::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $this->apiCompany->id)
                ->exists()
        );
    }
}
