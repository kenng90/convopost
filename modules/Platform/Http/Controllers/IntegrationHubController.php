<?php

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Platform\Models\IntegrationConnector;

class IntegrationHubController extends Controller
{
    public function index(): View
    {
        $this->ownerOnly();
        $company = $this->getCompany();

        $connectors = IntegrationConnector::where('company_id', $company->id)->get();
        $available = config('platform.integrations', []);

        return view('platform::integrations.index', [
            'connectors' => $connectors,
            'available' => $available,
        ]);
    }

    public function connect(Request $request, string $provider): RedirectResponse
    {
        $this->ownerOnly();
        $company = $this->getCompany();
        $available = config("platform.integrations.{$provider}");

        if (! $available) {
            return redirect()->route('integrations.index')->withError(__('Unknown integration.'));
        }

        $validated = $request->validate([
            'api_key' => 'nullable|string|max:500',
            'webhook_url' => 'nullable|url|max:500',
        ]);

        IntegrationConnector::updateOrCreate(
            ['company_id' => $company->id, 'provider' => $provider],
            [
                'status' => 'connected',
                'credentials' => array_filter($validated),
                'connected_at' => now(),
            ]
        );

        return redirect()->route('integrations.index')->withStatus(__(':name connected.', ['name' => $available['name']]));
    }

    public function disconnect(string $provider): RedirectResponse
    {
        $this->ownerOnly();
        IntegrationConnector::where('company_id', $this->getCompany()->id)
            ->where('provider', $provider)
            ->delete();

        return redirect()->route('integrations.index')->withStatus(__('Integration disconnected.'));
    }

    public function testEvent(string $provider): RedirectResponse
    {
        $this->ownerOnly();
        $company = $this->getCompany();

        $event = app(\App\Services\Integrations\PlatformEventBus::class)->emit($company, 'integration.test', [
            'provider' => $provider,
            'phone' => $company->getConfig('whatsapp_phone_number', ''),
        ]);

        return redirect()->route('integrations.index')->withStatus(
            $event->status === 'delivered'
                ? __('Test event delivered to :name.', ['name' => $provider])
                : __('Test event recorded. Delivery: :status', ['status' => $event->status])
        );
    }
}
