<?php

namespace Modules\Wpbox\Messaging;

use App\Enums\MessagingChannelType;
use App\Models\Messaging\ChannelConnection;
use App\Models\Messaging\Conversation;
use App\Services\Messaging\Contracts\MessagingChannel;
use App\Services\Messaging\DTO\ChannelCapabilities;
use App\Services\Messaging\DTO\ChannelHealth;
use App\Services\Messaging\DTO\InboundBatch;
use App\Services\Messaging\DTO\MessageContent;
use App\Services\Messaging\DTO\SendResult;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Traits\Whatsapp;

class WhatsappChannel implements MessagingChannel
{
    use Whatsapp;

    public function channel(): MessagingChannelType
    {
        return MessagingChannelType::Whatsapp;
    }

    public function verifyWebhook(Request $request, ChannelConnection $connection): ?Response
    {
        return null;
    }

    public function parseInbound(Request $request, ChannelConnection $connection): InboundBatch
    {
        return new InboundBatch;
    }

    public function send(ChannelConnection $connection, Conversation $conversation, Message $message, MessageContent $content): SendResult
    {
        $contact = Contact::withoutGlobalScopes()->findOrFail($conversation->contact_id);

        $this->bindCompanyContext($connection);

        try {
            $this->sendMessageToWhatsApp($message, $contact);

            return new SendResult(true, $message->fb_message_id);
        } catch (\Throwable $th) {
            return new SendResult(false, null, $th->getMessage());
        }
    }

    public function healthCheck(ChannelConnection $connection): ChannelHealth
    {
        $token = $connection->accessToken();
        $phoneId = $connection->credential('phone_number_id', '');

        if ($token === '' || $phoneId === '') {
            return new ChannelHealth(false, __('WhatsApp credentials incomplete.'));
        }

        return new ChannelHealth(true, __('WhatsApp connected.'));
    }

    public function capabilities(): ChannelCapabilities
    {
        return new ChannelCapabilities(
            text: true,
            media: true,
            templates: true,
            campaigns: true,
            flows: true,
            requiresServiceWindow: true,
            serviceWindowHours: 24,
        );
    }

    private function bindCompanyContext(ChannelConnection $connection): void
    {
        session(['company_id' => $connection->company_id]);
    }
}
