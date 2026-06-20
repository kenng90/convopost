<?php

namespace Modules\Instagram\Http\Controllers;

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
            ->where('channel', MessagingChannelType::Instagram->value)
            ->first();

        $token = $connection?->webhook_token ?? auth()->user()->createToken('instagram-webhook')->plainTextToken;

        return view('instagram::setup', [
            'company' => $company,
            'webhookUrl' => route('messaging.webhook.receive', [
                'channel' => MessagingChannelType::Instagram->value,
                'token' => $token,
            ]),
            'verifyToken' => $token,
            'isConnected' => $company->getConfig('instagram_connected', 'no') === 'yes',
        ]);
    }

    public function store(Request $request, ChannelConnectionService $connections)
    {
        $validated = $request->validate([
            'page_id' => 'required|string',
            'instagram_account_id' => 'nullable|string',
            'page_access_token' => 'required|string',
            'webhook_token' => 'required|string',
        ]);

        $company = $this->getCompany();

        $company->setConfig('instagram_page_id', $validated['page_id']);
        $company->setConfig('instagram_account_id', $validated['instagram_account_id'] ?? '');
        $company->setConfig('instagram_page_access_token', $validated['page_access_token']);

        $connection = $connections->upsertMetaConnection(
            $company,
            MessagingChannelType::Instagram,
            $validated['page_id'],
            $validated['page_access_token'],
            ['instagram_account_id' => $validated['instagram_account_id'] ?? ''],
        );

        $connections->storeWebhookToken($connection, $validated['webhook_token']);

        return redirect()
            ->route('instagram.setup')
            ->withStatus(__('Instagram Direct connected successfully.'));
    }
}
