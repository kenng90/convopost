<?php

namespace Modules\Tiktok\Http\Controllers;

use App\Enums\MessagingChannelType;
use App\Http\Controllers\Controller;
use App\Models\Messaging\ChannelConnection;
use App\Services\Messaging\ChannelConnectionService;
use App\Services\PlanEntitlementResolver;
use App\Services\PlanUsageLimit;
use Illuminate\Http\Request;

class SetupController extends Controller
{
    public function index(PlanUsageLimit $planUsageLimit, PlanEntitlementResolver $entitlements)
    {
        $user = auth()->user();

        if ($user->hasRole('admin') && ! session()->has('impersonate')) {
            return redirect()->route('whatsapp.setup');
        }

        $plan = $planUsageLimit->resolvePlanForUser($user);
        if (! $plan || ! $entitlements->hasCapability($plan, 'inbox_tiktok')) {
            return redirect()
                ->route('plans.current')
                ->withError(__('This feature is not included in your plan.'));
        }

        $company = $this->getCompany();
        $connection = ChannelConnection::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('channel', MessagingChannelType::Tiktok->value)
            ->first();

        $token = $connection?->webhook_token ?? auth()->user()->createToken('tiktok-webhook')->plainTextToken;

        return view('tiktok::setup', [
            'company' => $company,
            'webhookUrl' => route('messaging.webhook.receive', [
                'channel' => MessagingChannelType::Tiktok->value,
                'token' => $token,
            ]),
            'verifyToken' => $token,
            'isConnected' => $company->getConfig('tiktok_connected', 'no') === 'yes',
            'connection' => $connection,
        ]);
    }

    public function store(Request $request, ChannelConnectionService $connections)
    {
        $validated = $request->validate([
            'business_id' => 'required|string',
            'access_token' => 'required|string',
            'refresh_token' => 'nullable|string',
            'webhook_token' => 'required|string',
        ]);

        $company = $this->getCompany();

        $company->setConfig('tiktok_business_id', $validated['business_id']);
        $company->setConfig('tiktok_access_token', $validated['access_token']);

        $connection = $connections->upsertTiktokConnection(
            $company,
            $validated['business_id'],
            $validated['access_token'],
            array_filter([
                'refresh_token' => $validated['refresh_token'] ?? null,
            ]),
        );

        $connections->storeWebhookToken($connection, $validated['webhook_token']);

        return redirect()
            ->route('tiktok.setup')
            ->withStatus(__('TikTok Business Messaging connected successfully.'));
    }
}
