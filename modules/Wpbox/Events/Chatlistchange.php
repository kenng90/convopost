<?php

namespace Modules\Wpbox\Events;

use App\Models\Messaging\Conversation;
use Carbon\Carbon;
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
        $contact = Contact::withoutGlobalScopes()
            ->select('id', 'last_message', 'last_reply_at', 'last_client_reply_at', 'is_last_message_by_contact', 'name', 'resolved_chat')
            ->find($this->contact);

        $window = app(\App\Services\WhatsApp\WhatsAppSessionWindow::class);
        $expiresAt = $contact ? $window->expiresAt($contact) : null;

        return [
            'contact_id' => (int) $this->contact,
            'company_id' => (int) $this->company,
            'contact' => (int) $this->contact,
            'last_message' => $contact?->last_message,
            'last_reply_at' => $contact?->last_reply_at
                ? Carbon::parse($contact->last_reply_at)->toIso8601String()
                : null,
            'last_client_reply_at' => $contact?->last_client_reply_at
                ? Carbon::parse($contact->last_client_reply_at)->toIso8601String()
                : null,
            'is_last_message_by_contact' => (bool) $contact?->is_last_message_by_contact,
            'resolved_chat' => (bool) $contact?->resolved_chat,
            'in_service_window' => $expiresAt !== null && $expiresAt->isFuture(),
            'service_window_expires_at' => $expiresAt?->toIso8601String(),
            'name' => $contact?->name,
            'inbox_kinds' => Conversation::inboxKindsForContact((int) $this->contact),
        ];
    }
}
