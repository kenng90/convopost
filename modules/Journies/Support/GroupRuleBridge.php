<?php

namespace Modules\Journies\Support;

use Modules\Journies\Events\ContactAddedToGroup;
use Modules\Wpbox\Models\Contact;

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
