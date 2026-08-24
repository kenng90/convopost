<?php

namespace App\Services\Collections;

class CollectionPolicy
{
    public function stkTimeoutSeconds(): int
    {
        return 120;
    }

    public function maxStkAttempts(): int
    {
        return 2;
    }

    public function stkRetryDelayMinutes(): int
    {
        return 15;
    }

    public function firstReminderHours(): int
    {
        return 2;
    }

    public function secondReminderHours(): int
    {
        return 24;
    }

    public function maxChaseStep(): int
    {
        return 4;
    }
}
