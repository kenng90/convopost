<?php

namespace Modules\Wpbox\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Wpbox\Support\ChatBroadcastChannel;

class AgentReplies implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;

    public $message;

    public $contact;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, $message, $contact)
    {
        $this->user = $user;
        $this->message = $message;
        $this->contact = $contact;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel(ChatBroadcastChannel::name(
            (int) $this->contact->company_id,
            (int) $this->contact->id,
        ));
    }

    public function broadcastAs()
    {
        return 'general';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'value' => $this->message->value,
                'original_message' => $this->message->original_message ?? '',
                'header_text' => $this->message->header_text,
                'header_image' => $this->message->header_image,
                'header_document' => $this->message->header_document,
                'header_video' => $this->message->header_video,
                'header_audio' => $this->message->header_audio,
                'header_location' => $this->message->header_location,
                'footer_text' => $this->message->footer_text,
                'buttons' => $this->message->buttons,
                'is_message_by_contact' => (bool) $this->message->is_message_by_contact,
                'is_campign_messages' => (bool) $this->message->is_campign_messages,
                'is_note' => (bool) ($this->message->is_note ?? false),
                'is_call_brief' => (bool) ($this->message->is_call_brief ?? false),
                'sender_name' => $this->message->sender_name,
                'error' => $this->message->error,
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
            'contact' => [
                'id' => $this->contact->id,
            ],
        ];
    }
}
