<?php

namespace Tests\Unit;

use Modules\Embeddedlogin\Services\EmbeddedSignupCompletionService;
use Modules\Embeddedlogin\Services\EmbeddedSignupFlowResolver;
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

    public function test_flow_resolver_hides_omni_without_config_id(): void
    {
        config([
            'embeddedlogin.config_id' => 'wa-config',
            'embeddedlogin.omni_config_id' => '',
        ]);

        $options = app(EmbeddedSignupFlowResolver::class)->optionsForUser(null);

        $this->assertFalse($options['omnichannel_available']);
    }

    public function test_flow_resolver_hides_omni_when_config_ids_are_identical(): void
    {
        config([
            'embeddedlogin.config_id' => 'same-config',
            'embeddedlogin.omni_config_id' => 'same-config',
        ]);

        $options = app(EmbeddedSignupFlowResolver::class)->optionsForUser(null);

        $this->assertFalse($options['omnichannel_available']);
    }
}
