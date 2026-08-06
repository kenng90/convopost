<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\Credit;
use App\Models\User;
use App\Services\Platform\ManagedAiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AdminCreditGrantService
{
    public function __construct(
        private readonly ManagedAiService $managedAi,
    ) {
    }

    /**
     * @return array{messaging_granted: float, ai_granted: int, credit: ?Credit}
     */
    public function grant(
        Company $company,
        User $admin,
        float $messagingCredits = 0,
        int $aiCredits = 0,
        ?Carbon $messagingExpiresAt = null,
        ?string $note = null,
    ): array {
        $owner = $company->user;
        if (! $owner) {
            throw new \InvalidArgumentException('Company has no owner user.');
        }

        $messagingGranted = 0.0;
        $aiGranted = 0;
        $credit = null;

        if ($messagingCredits > 0) {
            if (! config('settings.enable_credits', false)) {
                throw new \RuntimeException('Messaging credits are disabled.');
            }

            $expiresAt = $messagingExpiresAt ?? Carbon::now()->addDays(30);
            $source = $this->buildMessagingSource($admin, $note);
            $credit = $owner->addCredits($messagingCredits, $source, $expiresAt);
            $messagingGranted = $messagingCredits;
        }

        if ($aiCredits > 0) {
            if (! config('managed-ai.enabled', true)) {
                throw new \RuntimeException('Managed AI credits are disabled.');
            }

            // Sync the billing period first so resetIfNewPeriod does not wipe this grant.
            $this->managedAi->resetIfNewPeriod($company);
            $this->managedAi->grantBonusCredits($owner, $aiCredits);
            $aiGranted = $aiCredits;
        }

        Log::info('Admin granted credits', [
            'admin_id' => $admin->id,
            'company_id' => $company->id,
            'owner_id' => $owner->id,
            'messaging_credits' => $messagingGranted,
            'ai_credits' => $aiGranted,
            'messaging_expires_at' => $messagingExpiresAt?->toDateString(),
            'note' => $note,
            'credit_id' => $credit?->id,
        ]);

        return [
            'messaging_granted' => $messagingGranted,
            'ai_granted' => $aiGranted,
            'credit' => $credit,
        ];
    }

    private function buildMessagingSource(User $admin, ?string $note): string
    {
        $parts = [
            'admin:manual',
            (string) $admin->id,
            now()->format('YmdHis'),
        ];

        if (filled($note)) {
            $slug = substr(preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($note)) ?? '', 0, 40);
            if ($slug !== '') {
                $parts[] = trim($slug, '-');
            }
        }

        return implode(':', $parts);
    }
}
