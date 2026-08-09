<?php

namespace Modules\Embeddedlogin\Services;

use App\Services\PlanEntitlementResolver;
use App\Services\PlanUsageLimit;
use Illuminate\Contracts\Auth\Authenticatable;

class EmbeddedSignupFlowResolver
{
    public function __construct(
        private readonly PlanUsageLimit $planUsageLimit,
        private readonly PlanEntitlementResolver $entitlements,
    ) {
    }

    /**
     * @return array{
     *   whatsapp_config_id: string,
     *   omni_config_id: string,
     *   session_info_version: string,
     *   default_flow: string,
     *   omnichannel_available: bool,
     *   solution_id: string,
     * }
     */
    public function optionsForUser(?Authenticatable $user): array
    {
        $whatsappConfigId = (string) config('embeddedlogin.config_id', '');
        $omniConfigId = (string) config('embeddedlogin.omni_config_id', '');
        $omnichannelAvailable = $omniConfigId !== ''
            && $omniConfigId !== $whatsappConfigId
            && $this->userCanUseOmnichannel($user);

        return [
            'whatsapp_config_id' => $whatsappConfigId,
            'omni_config_id' => $omniConfigId,
            'session_info_version' => (string) config('embeddedlogin.session_info_version', '3'),
            'default_flow' => (string) config('embeddedlogin.default_flow', 'whatsapp_only'),
            'omnichannel_available' => $omnichannelAvailable,
            'solution_id' => (string) config('embeddedlogin.solution_id', ''),
        ];
    }

    private function userCanUseOmnichannel(?Authenticatable $user): bool
    {
        if (! $user instanceof \App\Models\User) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        $plan = $this->planUsageLimit->resolvePlanForUser($user);

        if (! $plan) {
            return false;
        }

        return $this->entitlements->hasCapability($plan, 'inbox_instagram')
            || $this->entitlements->hasCapability($plan, 'inbox_messenger');
    }
}
