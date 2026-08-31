<?php

namespace App\Providers;

use App\Services\Messaging\ChannelConnectionService;
use App\Services\Messaging\ChannelWebhookRouter;
use App\Services\Messaging\ConversationService;
use App\Services\Messaging\InboundMessageProcessor;
use App\Services\Messaging\MessagingChannelRegistry;
use App\Services\Messaging\MetaMessagingParser;
use App\Services\Messaging\OutboundMessageService;
use Illuminate\Support\ServiceProvider;
use Modules\Instagram\Messaging\InstagramChannel;
use Modules\Messenger\Messaging\MessengerChannel;
use Modules\Tiktok\Messaging\TiktokChannel;
use Modules\Wpbox\Messaging\WhatsappChannel;

class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MessagingChannelRegistry::class, function ($app) {
            $registry = new MessagingChannelRegistry;
            $registry->register($app->make(WhatsappChannel::class));
            $registry->register($app->make(InstagramChannel::class));
            $registry->register($app->make(MessengerChannel::class));
            $registry->register($app->make(TiktokChannel::class));

            return $registry;
        });

        $this->app->singleton(ChannelConnectionService::class);
        $this->app->singleton(ConversationService::class);
        $this->app->singleton(InboundMessageProcessor::class);
        $this->app->singleton(OutboundMessageService::class);
        $this->app->singleton(ChannelWebhookRouter::class);
        $this->app->singleton(MetaMessagingParser::class);
    }

    public function boot(): void
    {
        //
    }
}
