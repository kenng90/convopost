<?php

namespace Modules\Instagram\Messaging;

use App\Enums\MessagingChannelType;
use App\Services\Messaging\Channels\AbstractMetaMessagingChannel;

class InstagramChannel extends AbstractMetaMessagingChannel
{
    public function channel(): MessagingChannelType
    {
        return MessagingChannelType::Instagram;
    }
}
