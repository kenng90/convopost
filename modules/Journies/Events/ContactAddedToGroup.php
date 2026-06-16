<?php

namespace Modules\Journies\Events;

use Illuminate\Queue\SerializesModels;
use Modules\Wpbox\Models\Contact;

class ContactAddedToGroup
{
    use SerializesModels;

    public function __construct(public Contact $contact, public int $groupId)
    {
    }
}
