<?php

namespace Tests\Unit;

use App\Services\Billing\CreditBillingResolver;
use Carbon\Carbon;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Template;
use Tests\TestCase;

class CreditBillingResolverTest extends TestCase
{
    private CreditBillingResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(CreditBillingResolver::class);
    }

    public function test_resolves_free_service_window_reply(): void
    {
        $contact = new Contact([
            'last_client_reply_at' => now()->subHours(2),
        ]);

        $this->assertSame(
            'send_service_window_reply',
            $this->resolver->resolveInboxOutboundAction($contact),
        );
    }

    public function test_resolves_outside_window_reply(): void
    {
        $contact = new Contact([
            'last_client_reply_at' => now()->subHours(30),
        ]);

        $this->assertSame(
            'send_outside_window_reply',
            $this->resolver->resolveInboxOutboundAction($contact),
        );
    }

    public function test_resolves_bot_auto_reply_action(): void
    {
        $contact = new Contact([
            'last_client_reply_at' => now(),
        ]);

        $this->assertSame(
            'send_bot_auto_reply',
            $this->resolver->resolveInboxOutboundAction($contact, isBotAutoReply: true),
        );
    }

    public function test_resolves_campaign_template_category(): void
    {
        $marketing = new Template(['category' => 'MARKETING']);
        $utility = new Template(['category' => 'UTILITY']);

        $this->assertSame('send_campaign_marketing', $this->resolver->resolveCampaignTemplateAction($marketing));
        $this->assertSame('send_template_utility', $this->resolver->resolveCampaignTemplateAction($utility));
    }

    public function test_service_window_uses_configured_hours(): void
    {
        config(['credit-actions.service_window_hours' => 12]);

        $contact = new Contact([
            'last_client_reply_at' => Carbon::now()->subHours(11),
        ]);

        $this->assertTrue($this->resolver->isWithinServiceWindow($contact));

        $contact->last_client_reply_at = Carbon::now()->subHours(13);
        $this->assertFalse($this->resolver->isWithinServiceWindow($contact));
    }
}
