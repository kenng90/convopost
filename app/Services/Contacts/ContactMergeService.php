<?php

namespace App\Services\Contacts;

use App\Models\Messaging\ChannelIdentity;
use App\Models\Messaging\Conversation;
use Illuminate\Support\Facades\DB;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

class ContactMergeService
{
    /**
     * Merge $secondary into $primary. Identities, conversations, messages, and groups
     * move to the primary contact; secondary is soft-deleted.
     */
    public function merge(Contact $primary, Contact $secondary): Contact
    {
        if ((int) $primary->id === (int) $secondary->id) {
            throw new \InvalidArgumentException('Cannot merge a contact into itself.');
        }

        if ((int) $primary->company_id !== (int) $secondary->company_id) {
            throw new \InvalidArgumentException('Contacts must belong to the same company.');
        }

        return DB::transaction(function () use ($primary, $secondary) {
            ChannelIdentity::withoutGlobalScopes()
                ->where('contact_id', $secondary->id)
                ->get()
                ->each(function (ChannelIdentity $identity) use ($primary) {
                    $duplicate = ChannelIdentity::withoutGlobalScopes()
                        ->where('company_id', $identity->company_id)
                        ->where('channel', $identity->channel)
                        ->where('external_id', $identity->external_id)
                        ->where('contact_id', $primary->id)
                        ->exists();

                    if ($duplicate) {
                        $identity->delete();

                        return;
                    }

                    $identity->update(['contact_id' => $primary->id]);
                });

            Conversation::withoutGlobalScopes()
                ->where('contact_id', $secondary->id)
                ->update(['contact_id' => $primary->id]);

            Message::withoutGlobalScopes()
                ->where('contact_id', $secondary->id)
                ->update(['contact_id' => $primary->id]);

            foreach ($secondary->groups as $group) {
                $primary->groups()->syncWithoutDetaching([$group->id]);
            }

            if (($primary->phone === null || $primary->phone === '') && $secondary->phone) {
                $primary->phone = $secondary->phone;
            }

            if (($primary->email === null || $primary->email === '') && $secondary->email) {
                $primary->email = $secondary->email;
            }

            if (($primary->name === null || trim((string) $primary->name) === '') && $secondary->name) {
                $primary->name = $secondary->name;
            }

            $primary->has_chat = $primary->has_chat || $secondary->has_chat;
            $primary->save();

            $secondary->groups()->detach();
            $secondary->delete();

            return $primary->fresh();
        });
    }
}
