<?php

namespace Tests\Unit;

use Modules\Embeddedlogin\Http\Controllers\Main;
use Tests\TestCase;

class EmbeddedSignupWabidResolverTest extends TestCase
{
    public function test_resolve_wabid_prefers_whatsapp_business_management_scope(): void
    {
        $controller = new Main;
        $method = new \ReflectionMethod(Main::class, 'resolveWabidFromDebugToken');
        $method->setAccessible(true);

        $wabid = $method->invoke($controller, [
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
        $controller = new Main;
        $method = new \ReflectionMethod(Main::class, 'resolveWabidFromDebugToken');
        $method->setAccessible(true);

        $wabid = $method->invoke($controller, [
            'data' => [
                'granular_scopes' => [
                    ['scope' => 'whatsapp_business_messaging'],
                    ['scope' => 'public_profile'],
                ],
            ],
        ]);

        $this->assertNull($wabid);
    }
}
