<?php

namespace App\Traits;

use App\Models\Cost;
use App\Models\Credit;
use App\Models\CreditMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

trait HasCredit
{
    public function credits()
    {
        return $this->hasMany(Credit::class, 'user_id');
    }

    public function activeCredits()
    {
        return $this->credits()
            ->where(function ($query) {
                $query->whereNull('expiration_date')
                    ->orWhere('expiration_date', '>=', Carbon::now());
            })
            ->where('remaining_credit_amount', '>', 0)
            ->orderBy('expiration_date', 'asc');
    }

    public function getTotalRemainingCredits()
    {
        return $this->activeCredits()->sum('remaining_credit_amount');
    }

    public function addCredits(int|float $amount, ?string $source = null, ?Carbon $expirationDate = null)
    {
        return $this->credits()->create([
            'user_id' => $this->id,
            'credit_amount' => $amount,
            'remaining_credit_amount' => $amount,
            'used_credit_amount' => 0,
            'source' => $source,
            'expiration_date' => $expirationDate,
        ]);
    }

    public function hasEnoughCreditsByAction(string $action): bool
    {
        if (config('settings.enable_credits', false) == false) {
            return true;
        }
        $amount = (float) (Cost::where('action', $action)->first()->cost ?? 0);
        if ((float) $amount === -1.0) {
            $amount = 1;
        }

        return $this->hasEnoughCredits($amount);
    }

    public function hasEnoughCredits(int|float $amount): bool
    {
        if (config('settings.enable_credits', false) == false) {
            return true;
        }

        return $this->getTotalRemainingCredits() >= $amount;
    }

    public function useCreditsByAction(string $action, int|float $amountBasedOnUsage = 1, ?int $companyId = null): bool
    {
        if (config('settings.enable_credits', false) == false) {
            return true;
        }
        $cost = Cost::where('action', $action)->first() ?? null;
        $amount = $cost ? (float) $cost->cost : 0.0;
        if ((float) $amount === -1.0) {
            $amount = (float) $amountBasedOnUsage;
        }

        return $this->useCredits($amount, $action, $companyId);
    }

    public function useCredits(int|float $amount, string $action, ?int $companyId = null): bool
    {
        if (config('settings.enable_credits', false) == false) {
            return true;
        }

        $amount = round((float) $amount, 2);

        if ($this->getTotalRemainingCredits() < $amount) {
            return false;
        }

        $remainingToSpend = $amount;

        foreach ($this->activeCredits()->get() as $credit) {
            $spendFromThis = min($remainingToSpend, (float) $credit->remaining_credit_amount);

            $credit->used_credit_amount += $spendFromThis;
            $credit->remaining_credit_amount -= $spendFromThis;
            $credit->save();

            $data = [
                'credit_id' => $credit->id,
                'action' => $action,
                'amount' => $spendFromThis,
                'company_id' => $companyId,
            ];
            Log::info('useCredits', [$data]);

            CreditMovement::create($data);

            $remainingToSpend -= $spendFromThis;

            if ($remainingToSpend <= 0) {
                break;
            }
        }

        return true;
    }

    public function getTotalRemainingCreditsAndPercentageUsed(): array
    {
        return [$this->getTotalRemainingCredits(), $this->getPercentageOfCreditsUsed()];
    }

    public function getPercentageOfCreditsUsed()
    {
        $totalCredits = $this->credits()
            ->where(function ($query) {
                $query->whereNull('expiration_date')
                    ->orWhere('expiration_date', '>=', now());
            })
            ->sum('credit_amount');
        $usedCredits = $this->credits()
            ->where(function ($query) {
                $query->whereNull('expiration_date')
                    ->orWhere('expiration_date', '>=', now());
            })
            ->sum('used_credit_amount');

        $percentage = $totalCredits > 0 ? round(($usedCredits / $totalCredits) * 100) : 0;

        return [$percentage, $totalCredits, $usedCredits];
    }
}
