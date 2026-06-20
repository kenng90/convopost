<?php

namespace Tests\Unit;

use Modules\Embeddedlogin\Services\EmbeddedSignupCompletionService;
use Modules\Embeddedlogin\Services\EmbeddedSignupSession;
use Tests\TestCase;

class EmbeddedSignupWabidResolverTest extends TestCase
{
    public function test_resolve_wabid_prefers_whatsapp_business_management_scope(): void
    {
        $service = app(EmbeddedSignupCompletionService::class);

        $wabid = $service->resolveWabidFromDebugToken([
            'data' => [
                'granular_scopes' => [
                    [
                        'scope' => 'whatsapp_business_management',
                        'target_ids' => ['111222333'],
                    ],
                    [
                        'scope' => 'whatsapp_business_messaging',
                        'target_ids' => ['999888777'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('111222333', $wabid);
    }

    public function test_resolve_wabid_returns_null_when_no_target_ids(): void
    {
        $service = app(EmbeddedSignupCompletionService::class);

        $wabid = $service->resolveWabidFromDebugToken([
            'data' => [
                'granular_scopes' => [
                    ['scope' => 'whatsapp_business_messaging'],
                    ['scope' => 'public_profile'],
                ],
            ],
        ]);

        $this->assertNull($wabid);
    }

    public function test_session_parses_v4_finish_payload_fields(): void
    {
        $session = EmbeddedSignupSession::fromRequest([
            'flow' => 'omnichannel',
            'waba_id' => 'waba-1',
            'phone_number_id' => 'phone-1',
            'page_ids' => json_encode(['page-99']),
            'instagram_account_ids' => json_encode(['ig-88']),
        ]);

        $this->assertTrue($session->isOmnichannel());
        $this->assertSame('page-99', $session->pageId);
        $this->assertSame('ig-88', $session->instagramAccountId);
    }
}
