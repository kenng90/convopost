<?php

namespace Modules\Messenger\Http\Controllers;

use App\Enums\MessagingChannelType;
use App\Http\Controllers\Controller;
use App\Models\Messaging\ChannelConnection;
use App\Services\Messaging\ChannelConnectionService;
use Illuminate\Http\Request;

class SetupController extends Controller
{
    public function index()
    {
        $company = $this->getCompany();
        $connection = ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('channel', MessagingChannelType::Messenger->value)
            ->first();

        $token = $connection?->webhook_token ?? auth()->user()->createToken('messenger-webhook')->plainTextToken;

        return view('messenger::setup', [
            'company' => $company,
            'webhookUrl' => route('messaging.webhook.receive', [
                'channel' => MessagingChannelType::Messenger->value,
                'token' => $token,
            ]),
            'verifyToken' => $token,
            'isConnected' => $company->getConfig('messenger_connected', 'no') === 'yes',
        ]);
    }

    public function store(Request $request, ChannelConnectionService $connections)
    {
        $validated = $request->validate([
            'page_id' => 'required|string',
            'page_access_token' => 'required|string',
            'webhook_token' => 'required|string',
        ]);

        $company = $this->getCompany();

        $company->setConfig('messenger_page_id', $validated['page_id']);
        $company->setConfig('messenger_page_access_token', $validated['page_access_token']);

        $connection = $connections->upsertMetaConnection(
            $company,
            MessagingChannelType::Messenger,
            $validated['page_id'],
            $validated['page_access_token'],
        );

        $connections->storeWebhookToken($connection, $validated['webhook_token']);

        return redirect()
            ->route('messenger.setup')
            ->withStatus(__('Facebook Messenger connected successfully.'));
    }
}
