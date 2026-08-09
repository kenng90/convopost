<?php

namespace Modules\Messenger\Messaging;

use App\Enums\MessagingChannelType;
use App\Services\Messaging\Channels\AbstractMetaMessagingChannel;

class MessengerChannel extends AbstractMetaMessagingChannel
{
    public function channel(): MessagingChannelType
    {
        return MessagingChannelType::Messenger;
    }
}
