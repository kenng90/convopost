<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Services\Messaging\ChannelWebhookRouter;
use Illuminate\Http\Request;

class ChannelWebhookController extends Controller
{
    public function receive(Request $request, string $channel, string $token, ChannelWebhookRouter $router)
    {
        return $router->handle($request, $channel, $token);
    }
}
