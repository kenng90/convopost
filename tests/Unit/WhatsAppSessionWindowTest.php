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
        $this->assertNull($window->expiresAt($contact));
    }

    public function test_session_open_within_twenty_four_hours(): void
    {
        $contact = new Contact(['last_client_reply_at' => Carbon::now()->subHours(2)]);
        $window = new WhatsAppSessionWindow;

        $this->assertTrue($window->isOpen($contact));
        $this->assertTrue($window->expiresAt($contact)->isFuture());
    }

    public function test_session_closed_after_twenty_four_hours(): void
    {
        $contact = new Contact(['last_client_reply_at' => Carbon::now()->subHours(25)]);
        $window = new WhatsAppSessionWindow;

        $this->assertFalse($window->isOpen($contact));
    }

    public function test_session_open_when_last_message_is_from_contact_even_without_last_client_reply_at(): void
    {
        $contact = new Contact([
            'last_client_reply_at' => null,
            'is_last_message_by_contact' => true,
            'last_reply_at' => Carbon::now()->subMinutes(10),
        ]);
        $window = new WhatsAppSessionWindow;

        $this->assertTrue($window->isOpen($contact));
        $this->assertNotNull($window->expiresAt($contact));
    }
}
