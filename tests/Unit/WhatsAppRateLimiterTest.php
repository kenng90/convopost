<?php

namespace Tests\Unit;

use App\Services\Campaign\WhatsAppRateLimiter;
use Tests\TestCase;

class WhatsAppRateLimiterTest extends TestCase
{
    public function test_default_cloud_api_limit_is_ten_tps(): void
    {
        config(['wpbox.whatsapp_messages_per_second' => 10]);

        $this->assertSame(10, (new WhatsAppRateLimiter)->perSecondLimit());
    }

    public function test_limit_never_drops_below_one(): void
    {
        config(['wpbox.whatsapp_messages_per_second' => 0]);

        $this->assertSame(1, (new WhatsAppRateLimiter)->perSecondLimit());
    }
}
