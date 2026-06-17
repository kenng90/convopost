<?php

namespace Modules\Journies\Support;

use Modules\Contacts\Models\Contact;
use Modules\Journies\Events\ContactAddedToGroup;

class GroupRuleBridge
{
    public static function contactAddedToGroups(Contact $contact, array $groupIds): void
    {
        foreach ($groupIds as $groupId) {
            if ($groupId) {
                event(new ContactAddedToGroup($contact, (int) $groupId));
            }
        }
    }
}
