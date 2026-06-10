<?php

namespace Tests\Unit;

use Modules\Whatsappcall\Support\CallBriefFormatter;
use Tests\TestCase;

class CallBriefFormatterTest extends TestCase
{
    public function test_it_includes_summary_bullets_and_transcript_in_plain_text(): void
    {
        $text = CallBriefFormatter::toPlainText([
            'handled_by' => 'ai',
            'duration_seconds' => 95,
            'summary_bullets' => [
                'Agent: Hello, how can I help?',
                'Caller: I need support with billing.',
            ],
            'transcript_excerpt' => "[AI] Hello\n[Caller] Billing help please",
        ]);

        $this->assertStringContainsString('Summary', $text);
        $this->assertStringContainsString('Agent: Hello, how can I help?', $text);
        $this->assertStringContainsString('Transcript', $text);
        $this->assertStringContainsString('[Caller] Billing help please', $text);
    }
}
