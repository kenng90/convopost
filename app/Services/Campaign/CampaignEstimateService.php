<?php

namespace App\Services\Campaign;

use App\Models\Company;
use App\Services\Billing\CreditBillingResolver;
use App\Services\Billing\CreditCharger;
use App\Services\Billing\CreditCostService;
use App\Services\Campaign\Templates\EmailTemplateProvider;
use App\Services\Campaign\Templates\SmsTemplateProvider;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Template;

class CampaignEstimateService
{
    public function __construct(
        private readonly CampaignAudienceResolver $audience,
        private readonly CampaignFileParser $fileParser,
        private readonly CreditBillingResolver $billingResolver,
        private readonly CreditCharger $charger,
        private readonly CreditCostService $costs,
    ) {
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{recipient_count: int, subscribed_count: int, excluded_count: int, credit_per_message: float, total_credits: float, can_afford: bool, credit_action: string}
     */
    public function estimate(Company $company, Template $template, array $options = []): array
    {
        return $this->estimateForChannel($company, Campaign::CHANNEL_WHATSAPP, $options, $template);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{recipient_count: int, subscribed_count: int, excluded_count: int, credit_per_message: float, total_credits: float, can_afford: bool, credit_action: string}
     */
    public function estimateForChannel(
        Company $company,
        string $channel,
        array $options = [],
        ?Template $template = null,
    ): array {
        $recipientCount = $this->resolveRecipientCount($company, $channel, $options);
        $creditAction = $this->resolveCreditAction($channel, $template);
        $creditPerMessage = $this->costs->getActionCost($creditAction, 1);
        $totalCredits = $creditPerMessage * $recipientCount;

        return [
            'recipient_count' => $options['total_count'] ?? $recipientCount,
            'subscribed_count' => $recipientCount,
            'excluded_count' => max(0, ($options['total_count'] ?? $recipientCount) - $recipientCount),
            'credit_per_message' => $creditPerMessage,
            'total_credits' => $totalCredits,
            'can_afford' => $this->charger->canCharge($company, $creditAction, max(1, $recipientCount)),
            'credit_action' => $creditAction,
        ];
    }

    /**
     * @return array{recipient_count: int, subscribed_count: int, excluded_count: int, credit_per_message: float, total_credits: float, can_afford: bool, credit_action: string}
     */
    public function estimateForCampaign(Campaign $campaign): array
    {
        $campaign->loadMissing(['template', 'company']);

        return $this->estimateForChannel(
            $campaign->company,
            $campaign->channel ?? Campaign::CHANNEL_WHATSAPP,
            [
                'group_id' => $campaign->group_id,
                'segment_id' => $campaign->segment_id,
                'contact_id' => $campaign->contact_id,
            ],
            $campaign->template
        );
    }

    private function resolveCreditAction(string $channel, ?Template $template): string
    {
        return match ($channel) {
            Campaign::CHANNEL_SMS => app(SmsTemplateProvider::class)->creditAction(),
            Campaign::CHANNEL_EMAIL => app(EmailTemplateProvider::class)->creditAction(),
            default => $this->billingResolver->resolveCampaignTemplateAction($template),
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveRecipientCount(Company $company, string $channel, array $options): int
    {
        if (! empty($options['file_row_count'])) {
            return (int) $options['file_row_count'];
        }

        if (! empty($options['quick_phone_count'])) {
            return (int) $options['quick_phone_count'];
        }

        $options['channel'] = $channel;

        return $this->audience->subscribedCount($company, $options);
    }
}
