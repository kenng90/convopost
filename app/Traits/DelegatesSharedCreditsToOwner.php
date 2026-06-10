<?php

namespace App\Traits;

use Carbon\Carbon;

trait DelegatesSharedCreditsToOwner
{
    public function getTotalRemainingCredits()
    {
        return $this->user?->getTotalRemainingCredits() ?? 0;
    }

    public function addCredits(int|float $amount, ?string $source = null, ?Carbon $expirationDate = null)
    {
        return $this->user?->addCredits($amount, $source, $expirationDate);
    }

    public function hasEnoughCreditsByAction(string $action): bool
    {
        return $this->user?->hasEnoughCreditsByAction($action) ?? true;
    }

    public function hasEnoughCredits(int|float $amount): bool
    {
        return $this->user?->hasEnoughCredits($amount) ?? true;
    }

    public function useCreditsByAction(string $action, int|float $amountBasedOnUsage = 1): bool
    {
        return $this->user?->useCreditsByAction($action, $amountBasedOnUsage, $this->id) ?? false;
    }

    public function useCredits(int|float $amount, string $action): bool
    {
        return $this->user?->useCredits($amount, $action, $this->id) ?? false;
    }

    public function getTotalRemainingCreditsAndPercentageUsed(): array
    {
        if ($this->user === null) {
            return [0, [0, 0, 0]];
        }

        return $this->user->getTotalRemainingCreditsAndPercentageUsed();
    }

    public function getPercentageOfCreditsUsed()
    {
        return $this->user?->getPercentageOfCreditsUsed() ?? [0, 0, 0];
    }
}
