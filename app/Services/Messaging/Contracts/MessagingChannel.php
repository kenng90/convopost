<?php

namespace App\Services\Messaging\Contracts;

use App\Enums\MessagingChannelType;
use App\Models\Messaging\ChannelConnection;
use App\Models\Messaging\Conversation;
use App\Services\Messaging\DTO\ChannelCapabilities;
use App\Services\Messaging\DTO\ChannelHealth;
use App\Services\Messaging\DTO\InboundBatch;
use App\Services\Messaging\DTO\MessageContent;
use App\Services\Messaging\DTO\SendResult;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Wpbox\Models\Message;

interface MessagingChannel
{
    public function channel(): MessagingChannelType;

    public function verifyWebhook(Request $request, ChannelConnection $connection): ?Response;

    public function parseInbound(Request $request, ChannelConnection $connection): InboundBatch;

    public function send(ChannelConnection $connection, Conversation $conversation, Message $message, MessageContent $content): SendResult;

    public function healthCheck(ChannelConnection $connection): ChannelHealth;

    public function capabilities(): ChannelCapabilities;
}
