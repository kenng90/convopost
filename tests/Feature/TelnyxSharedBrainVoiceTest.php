<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\Telephony\TelephonyProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Voicecall\Models\VoiceCall;
use Modules\Voicecall\Models\VoicePhoneNumber;
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TelnyxSharedBrainVoiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private string $to = '+254711111111';

    private string $from = '+254700000099';

    private string $callControlId = 'v3:test-call-control-id';

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $this->company->id]);

        $this->company->setConfig('telephony_provider', TelephonyProvider::TELNYX);
        $this->company->setConfig('TELNYX_API_KEY', 'KEY_TEST_telnyx');
        $this->company->setConfig('TELNYX_CONNECTION_ID', 'conn-test');

        VoicePhoneNumber::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'provider' => TelephonyProvider::TELNYX,
            'phone_number' => $this->to,
            'friendly_name' => 'Telnyx line',
            'ai_greeting' => 'Thanks for calling. How can I help?',
            'handoff_phrases' => ['speak to a person'],
            'required_field_keys' => ['name', 'phone'],
            'is_active' => true,
        ]);
    }

    public function test_telnyx_gather_uses_action_agent_then_continues_the_call(): void
    {
        Http::fake([
            'api.telnyx.com/*' => Http::response(['data' => ['result' => 'ok']], 200),
        ]);

        Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Voice Shopper',
            'phone' => ltrim($this->from, '+'),
            'enabled_ai_bot' => true,
        ]);

        $this->postTelnyx('call.initiated', [
            'call_control_id' => $this->callControlId,
            'direction' => 'incoming',
            'from' => $this->from,
            'to' => $this->to,
        ])->assertOk();

        $this->postTelnyx('call.answered', [
            'call_control_id' => $this->callControlId,
        ])->assertOk();

        $this->postTelnyx('call.speak.ended', [
            'call_control_id' => $this->callControlId,
        ])->assertOk();

        $this->postTelnyx('call.gather.ended', [
            'call_control_id' => $this->callControlId,
            'speech' => [
                'alternatives' => [
                    ['transcript' => 'I want to buy a product from the catalog'],
                ],
            ],
        ])->assertOk();

        $voiceCall = VoiceCall::where('provider_call_id', $this->callControlId)->first();

        $this->assertNotNull($voiceCall);
        $this->assertSame(TelephonyProvider::TELNYX, $voiceCall->provider);
        $this->assertTrue((bool) ($voiceCall->structured['shared_brain'] ?? false));
        $this->assertSame('gathering', $voiceCall->structured['telnyx_stage'] ?? null);
        $this->assertFalse((bool) $voiceCall->handoff_requested);
        $this->assertStringContainsString('[Caller] I want to buy a product from the catalog', (string) $voiceCall->transcript);
        $this->assertStringContainsString('[Agent]', (string) $voiceCall->transcript);
        $this->assertStringContainsString('catalog', mb_strtolower((string) $voiceCall->transcript));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telnyx.com')
            && str_contains($request->url(), '/actions/answer'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/actions/gather_using_speak')
            && str_contains((string) $request->body(), 'catalog'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'twilio.com')
            || str_contains($request->url(), 'api.twilio.com'));
    }

    public function test_telnyx_handoff_disables_bot_and_hangs_up(): void
    {
        Http::fake([
            'api.telnyx.com/*' => Http::response(['data' => ['result' => 'ok']], 200),
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Voice Handoff',
            'phone' => ltrim($this->from, '+'),
            'enabled_ai_bot' => true,
        ]);

        $this->postTelnyx('call.initiated', [
            'call_control_id' => $this->callControlId,
            'direction' => 'incoming',
            'from' => $this->from,
            'to' => $this->to,
        ])->assertOk();

        $this->postTelnyx('call.answered', [
            'call_control_id' => $this->callControlId,
        ])->assertOk();

        $this->postTelnyx('call.speak.ended', [
            'call_control_id' => $this->callControlId,
        ])->assertOk();

        $this->postTelnyx('call.gather.ended', [
            'call_control_id' => $this->callControlId,
            'speech' => [
                'alternatives' => [
                    ['transcript' => 'I want to talk to a human'],
                ],
            ],
        ])->assertOk();

        $voiceCall = VoiceCall::where('provider_call_id', $this->callControlId)->first();

        $this->assertNotNull($voiceCall);
        $this->assertTrue((bool) $voiceCall->handoff_requested);
        $this->assertSame('done', $voiceCall->structured['telnyx_stage'] ?? null);
        $this->assertTrue((bool) ($voiceCall->structured['shared_brain'] ?? false));
        $this->assertFalse((bool) $contact->fresh()->enabled_ai_bot);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/actions/hangup'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'twilio.com'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postTelnyx(string $eventType, array $payload)
    {
        return $this->postJson(route('voicecall.webhook.telnyx'), [
            'data' => [
                'event_type' => $eventType,
                'payload' => $payload,
            ],
        ]);
    }
}
