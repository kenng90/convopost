<?php

namespace Modules\Wpbox\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Wpbox\Models\Contact;

class Chatlistchange implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $contact;

    public $company;

    public function __construct($contact, $company)
    {
        $this->company = $company;
        $this->contact = $contact;
    }

    public function broadcastOn()
    {
        return new Channel('chatupdate.'.$this->company);
    }

    public function broadcastAs()
    {
        return 'general';
    }

    public function broadcastWith(): array
    {
        $contact = Contact::query()
            ->select('id', 'last_message', 'last_reply_at', 'is_last_message_by_contact', 'name', 'resolved_chat')
            ->find($this->contact);

        return [
            'contact_id' => (int) $this->contact,
            'company_id' => (int) $this->company,
            'contact' => (int) $this->contact,
            'last_message' => $contact?->last_message,
            'last_reply_at' => $contact?->last_reply_at?->toIso8601String(),
            'is_last_message_by_contact' => (bool) $contact?->is_last_message_by_contact,
            'resolved_chat' => (bool) $contact?->resolved_chat,
            'name' => $contact?->name,
        ];
    }
}
