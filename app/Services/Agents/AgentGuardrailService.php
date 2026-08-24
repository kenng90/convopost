<?php

namespace App\Services\Agents;

use App\Models\Company;
use App\Services\Trust\ConsentService;
use Modules\Wpbox\Models\Contact;

class AgentGuardrailService
{
    public function maxChargeAmount(Company $company): float
    {
        return (float) $company->getConfig('action_agent_max_charge', 50000);
    }

    public function requiresChargeConfirmation(Company $company): bool
    {
        return $company->getConfig('action_agent_require_charge_confirm', 'yes') !== 'no';
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|null Error payload when blocked
     */
    public function block(Company $company, Contact $contact, string $tool, array $arguments = []): ?array
    {
        if (in_array($tool, ['send_payment', 'create_booking', 'create_invoice'], true)
            && app(ConsentService::class)->hasOptOut($company, $contact)) {
            return ['ok' => false, 'error' => 'Customer has opted out of automated actions'];
        }

        if (in_array($tool, ['send_payment', 'create_invoice'], true)) {
            $amount = (float) ($arguments['amount'] ?? 0);
            $max = $this->maxChargeAmount($company);
            if ($amount > $max) {
                return [
                    'ok' => false,
                    'error' => 'Amount exceeds the agent charge limit of '.$max,
                    'max_amount' => $max,
                ];
            }
        }

        if ($tool === 'send_payment'
            && $this->requiresChargeConfirmation($company)
            && empty($arguments['confirmed'])) {
            return [
                'ok' => false,
                'needs_confirmation' => true,
                'amount' => (float) ($arguments['amount'] ?? 0),
                'error' => 'Ask the customer to confirm the charge before collecting payment',
            ];
        }

        return null;
    }
}
