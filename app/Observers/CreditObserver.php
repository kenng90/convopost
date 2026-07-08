<?php

namespace App\Observers;

use App\Models\Credit;
use App\Services\HostPinnacle\HostPinnacleCreditSync;

class CreditObserver
{
    public function __construct(
        private readonly HostPinnacleCreditSync $creditSync,
    ) {
    }

    public function created(Credit $credit): void
    {
        $this->creditSync->syncCreditPurchase($credit);
    }
}
