<?php

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Outcomes\OutcomeMetricsService;
use App\Services\Outcomes\PlaybookInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OutcomesController extends Controller
{
    public function index(PlaybookInstaller $installer, OutcomeMetricsService $metrics): View
    {
        $this->ownerAndStaffOnly();
        $company = $this->getCompany();

        return view('platform::outcomes.index', [
            'status' => $installer->statusForCompany($company),
            'metrics' => $metrics->forCompany($company),
            'webhookUrls' => [
                'shopify' => url('/webhooks/commerce/shopify/'.$company->getConfig('plain_token', '')),
                'woocommerce' => url('/webhooks/commerce/woocommerce/'.$company->getConfig('plain_token', '')),
            ],
        ]);
    }

    public function install(Request $request, string $playbook, PlaybookInstaller $installer): RedirectResponse
    {
        $this->ownerOnly();

        $request->validate([
            'install_flow' => 'nullable|boolean',
            'force' => 'nullable|boolean',
        ]);

        $result = $installer->install(
            $this->getCompany(),
            $playbook,
            (bool) $request->boolean('install_flow', true),
            (bool) $request->boolean('force', false),
        );

        if (! ($result['success'] ?? false)) {
            return redirect()->route('outcomes.index')->withError($result['message'] ?? __('Install failed.'));
        }

        return redirect()->route('outcomes.index')->withStatus($result['message']);
    }

    public function installSuite(Request $request, PlaybookInstaller $installer): RedirectResponse
    {
        $this->ownerOnly();

        $result = $installer->installSuite(
            $this->getCompany(),
            (bool) $request->boolean('install_flow', true),
            (bool) $request->boolean('force', false),
        );

        if (! ($result['success'] ?? false)) {
            return redirect()->route('outcomes.index')->withError($result['message']);
        }

        return redirect()->route('outcomes.index')->withStatus($result['message']);
    }
}
