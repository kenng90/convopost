<?php

namespace Modules\Wpbox\Support;

class ChatBroadcastChannel
{
    public static function name(int $companyId, int $contactId): string
    {
        return "chat.{$companyId}.{$contactId}";
    }
}
