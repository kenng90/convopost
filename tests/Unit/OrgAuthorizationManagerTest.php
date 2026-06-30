<?php

namespace Tests\Unit;

use App\Services\OrgAuthorization;
use Tests\TestCase;

class OrgAuthorizationManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        OrgAuthorization::clearRouteModuleMapCache();
    }

    public function test_route_map_includes_platform_feature_routes(): void
    {
        $map = app(OrgAuthorization::class)->routeModuleMap();

        $this->assertSame('wpbox', $map['health-alerts.index']);
        $this->assertSame('wpbox', $map['customer360.show']);
        $this->assertSame('wpbox', $map['copilot.suggest']);
        $this->assertSame('flowmaker', $map['flow-templates.index']);
        $this->assertSame('flowmaker', $map['flow-templates.install']);
        $this->assertSame('flowmaker', $map['flows.create-from-template']);
    }

    public function test_activation_routes_are_protected_from_managers(): void
    {
        $protected = config('org-access.protected_routes');

        $this->assertContains('activation.index', $protected);
        $this->assertContains('integrations.index', $protected);
    }
}
