<?php

namespace Tests\Unit;

use App\Services\WhatsApp\WhatsAppSessionWindow;
use Carbon\Carbon;
use Modules\Wpbox\Models\Contact;
use Tests\TestCase;

class WhatsAppSessionWindowTest extends TestCase
{
    public function test_session_closed_when_no_client_reply(): void
    {
        $contact = new Contact(['last_client_reply_at' => null]);
        $window = new WhatsAppSessionWindow;

        $this->assertFalse($window->isOpen($contact));
        $this->assertFalse($window->isOpen(null));
    }

    public function test_session_open_within_twenty_four_hours(): void
    {
        $contact = new Contact(['last_client_reply_at' => Carbon::now()->subHours(2)]);
        $window = new WhatsAppSessionWindow;

        $this->assertTrue($window->isOpen($contact));
    }

    public function test_session_closed_after_twenty_four_hours(): void
    {
        $contact = new Contact(['last_client_reply_at' => Carbon::now()->subHours(25)]);
        $window = new WhatsAppSessionWindow;

        $this->assertFalse($window->isOpen($contact));
    }
}
