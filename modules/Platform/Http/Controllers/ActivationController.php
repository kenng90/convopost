<?php

namespace Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstallVerticalRequest;
use App\Services\Onboarding\VerticalGoLiveService;
use App\Services\Platform\ActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivationController extends Controller
{
    public function __construct(private ActivationService $activation)
    {
    }

    public function index(): View|RedirectResponse
    {
        $this->ownerOnly();

        $company = $this->getCompany();
        $this->activation->refreshAutoDetectedSteps($company);

        if ($this->activation->isComplete($company)) {
            return redirect()->route('dashboard');
        }

        if (! $this->activation->isWhatsappConnected($company)) {
            return redirect()->route('whatsapp.setup');
        }

        $goLive = app(VerticalGoLiveService::class);

        return view('platform::activation.index', [
            'company' => $company,
            'steps' => $this->activation->steps($company),
            'progress' => $this->activation->progressPercent($company),
            'verticals' => $goLive->packs(),
            'launch' => $goLive->lastLaunch($company),
        ]);
    }

    public function complete(): RedirectResponse
    {
        $this->ownerOnly();
        $company = $this->getCompany();
        $this->activation->refreshAutoDetectedSteps($company);

        if ($this->activation->isComplete($company)) {
            $this->activation->markComplete($company);

            return redirect()->route('dashboard')->withStatus(__('Activation complete! Your workspace is ready.'));
        }

        return redirect()->route('activation.index')->withError(__('Complete all steps before finishing activation.'));
    }

    public function skip(Request $request): RedirectResponse
    {
        $this->ownerOnly();
        $this->activation->skip($this->getCompany());

        return redirect()->route('dashboard')->withStatus(__('Setup wizard skipped. You can return anytime from the dashboard.'));
    }

    public function markTestMessage(Request $request): RedirectResponse
    {
        $this->ownerOnly();
        $this->activation->markTestMessageSent($this->getCompany());

        return redirect()->route('activation.index')->withStatus(__('Test message step recorded.'));
    }

    public function installVertical(InstallVerticalRequest $request, VerticalGoLiveService $goLive): RedirectResponse
    {
        $this->ownerOnly();

        $result = $goLive->install(
            $this->getCompany(),
            $request->validated('vertical'),
            (bool) $request->boolean('install_playbook', true),
            $request->validated('test_phone')
        );

        if (! ($result['success'] ?? false)) {
            return redirect()->route('activation.index')->withError($result['message']);
        }

        return redirect()->route('activation.index')->withStatus($result['message']);
    }
}
