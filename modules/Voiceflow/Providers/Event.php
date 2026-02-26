<?php

namespace Modules\Voiceflow\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Voiceflow\Listeners\RespondOnMessage;

class Event extends ServiceProvider
{
    protected $listen = [];

    protected $subscribe = [
        RespondOnMessage::class,
    ];
}