<?php

namespace Modules\Instagram\Http\Controllers;

use App\Enums\MessagingChannelType;
use App\Http\Controllers\Controller;
use App\Models\Messaging\ChannelConnection;
use App\Services\Messaging\ChannelConnectionService;
use App\Services\Messaging\MetaInstagramCredentialResolver;
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
        if (! $plan || ! $entitlements->hasCapability($plan, 'inbox_instagram')) {
            return redirect()
                ->route('plans.current')
                ->withError(__('This feature is not included in your plan.'));
        }

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
            'connection' => $connection,
        ]);
    }

    public function store(
        Request $request,
        ChannelConnectionService $connections,
        MetaInstagramCredentialResolver $resolver,
    ) {
        $validated = $request->validate([
            'page_id' => 'required|string',
            'instagram_account_id' => 'required|string',
            'page_access_token' => 'required|string',
            'webhook_token' => 'required|string',
        ]);

        $resolved = $resolver->resolve(
            $validated['page_id'],
            $validated['instagram_account_id'],
            $validated['page_access_token'],
        );

        $company = $this->getCompany();

        $company->setConfig('instagram_page_id', $resolved['page_id']);
        $company->setConfig('instagram_account_id', $resolved['instagram_account_id']);
        $company->setConfig('instagram_page_access_token', $resolved['page_access_token']);

        $connection = $connections->upsertMetaConnection(
            $company,
            MessagingChannelType::Instagram,
            $resolved['page_id'],
            $resolved['page_access_token'],
            [
                'instagram_account_id' => $resolved['instagram_account_id'],
                'page_name' => $resolved['page_name'],
                'ig_username' => $resolved['ig_username'],
            ],
        );

        $connections->storeWebhookToken($connection, $validated['webhook_token']);

        $label = trim(($resolved['page_name'] ?? '').' / @'.($resolved['ig_username'] ?? ''));

        return redirect()
            ->route('instagram.setup')
            ->withStatus(__('Instagram Direct connected successfully.').($label !== '/' ? ' '.$label : ''));
    }
}
