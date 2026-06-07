<?php

namespace App\Services;

use App\Models\Plans;
use App\Models\User;

class PlanEntitlementResolver
{
    /**
     * @return array<int, string>|null null = all capabilities allowed
     */
    public function getCapabilities(Plans $plan): ?array
    {
        $raw = $plan->getConfig('capabilities', null);

        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function hasCapability(Plans $plan, string $capability): bool
    {
        $capabilities = $this->getCapabilities($plan);

        return $capabilities === null || in_array($capability, $capabilities, true);
    }

    public function userHasCapability(User $user, string $capability): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $plan = app(PlanUsageLimit::class)->resolvePlanForUser($user);

        if ($plan === null) {
            return false;
        }

        return $this->hasCapability($plan, $capability);
    }
}
