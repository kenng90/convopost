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
}
