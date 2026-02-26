<?php

namespace Modules\Journies\Events;

use Illuminate\Queue\SerializesModels;

class ContactMovedToStage
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(public $contact, public $stage)
    {
        //
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }
}
