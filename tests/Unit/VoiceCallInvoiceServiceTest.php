<?php

namespace Tests\Unit;

use Modules\Whatsappcall\Services\VoiceCallInvoiceService;
use Modules\Whatsappcall\Services\VoiceInvoiceSender;
use Tests\TestCase;

class VoiceCallInvoiceServiceTest extends TestCase
{
    public function test_transcript_indicates_purchase_intent(): void
    {
        $service = new VoiceCallInvoiceService(new VoiceInvoiceSender);
        $method = new \ReflectionMethod($service, 'transcriptIndicatesOrder');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, 'I want to buy a yellow bowl please'));
        $this->assertFalse($method->invoke($service, 'What are your opening hours?'));
    }

    public function test_fuzzy_title_matches_partial_product_name(): void
    {
        $service = new VoiceCallInvoiceService(new VoiceInvoiceSender);
        $method = new \ReflectionMethod($service, 'fuzzyTitleScore');
        $method->setAccessible(true);

        $score = $method->invoke($service, 'i want to buy yellow bowl', 'yellow bowl');

        $this->assertGreaterThanOrEqual(15, $score);
    }

    public function test_extract_caller_lines_from_transcript(): void
    {
        $service = new VoiceCallInvoiceService(new VoiceInvoiceSender);
        $method = new \ReflectionMethod($service, 'extractCallerText');
        $method->setAccessible(true);

        $text = $method->invoke($service, "[Agent] Hello\n[Caller] I want to buy yellow bowl\n[Agent] Sure");

        $this->assertStringContainsString('yellow bowl', $text);
    }
}
