<?php

namespace Tests\Unit;

use Modules\Wpbox\Events\AgentReplies;
use Modules\Wpbox\Events\ContactReplies;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Support\ChatBroadcastChannel;
use Tests\TestCase;

class ChatBroadcastChannelTest extends TestCase
{
    public function test_channel_name_includes_company_and_contact_ids(): void
    {
        $this->assertSame('chat.12.34', ChatBroadcastChannel::name(12, 34));
    }

    public function test_contact_replies_event_broadcasts_on_company_scoped_channel(): void
    {
        $message = new Message([
            'contact_id' => 10,
            'company_id' => 3,
        ]);

        $contact = new Contact([
            'id' => 10,
            'company_id' => 3,
        ]);

        $event = new ContactReplies(null, $message, $contact);

        $this->assertSame('chat.3.10', $event->broadcastOn()->name);
    }

    public function test_agent_replies_event_broadcasts_on_company_scoped_channel(): void
    {
        $message = new Message([
            'contact_id' => 55,
            'company_id' => 7,
        ]);

        $contact = new Contact([
            'id' => 55,
            'company_id' => 7,
        ]);

        $event = new AgentReplies(null, $message, $contact);

        $this->assertSame('chat.7.55', $event->broadcastOn()->name);
    }
}
