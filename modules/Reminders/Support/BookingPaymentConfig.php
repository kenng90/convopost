<?php

namespace Modules\Reminders\Support;

use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\Source;

class BookingPaymentConfig
{
    public const STATUS_NOT_REQUIRED = 'not_required';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public static function fromSource(Source $source): array
    {
        return self::build(
            paymentRequired: (bool) $source->payment_required,
            totalAmount: $source->payment_amount !== null ? (float) $source->payment_amount : null,
            upfrontPercent: $source->payment_upfront_percent,
            currency: (string) ($source->payment_currency ?: 'KES'),
        );
    }

    public static function fromEvent(Event $event): array
    {
        return self::build(
            paymentRequired: (bool) $event->payment_required,
            totalAmount: $event->payment_amount !== null ? (float) $event->payment_amount : null,
            upfrontPercent: $event->payment_upfront_percent,
            currency: (string) ($event->payment_currency ?: 'KES'),
        );
    }

    /**
     * @return array{
     *     payment_required: bool,
     *     payment_total_amount: float|null,
     *     payment_upfront_percent: int,
     *     payment_amount: float|null,
     *     payment_currency: string
     * }
     */
    public static function build(bool $paymentRequired, ?float $totalAmount, mixed $upfrontPercent, string $currency): array
    {
        $percent = self::normalizeUpfrontPercent($upfrontPercent);
        $total = $totalAmount !== null && $totalAmount > 0 ? round($totalAmount, 2) : null;
        $upfront = $total !== null ? self::upfrontAmount($total, $percent) : null;

        return [
            'payment_required' => $paymentRequired && $upfront !== null && $upfront > 0,
            'payment_total_amount' => $total,
            'payment_upfront_percent' => $percent,
            'payment_amount' => $upfront,
            'payment_currency' => strtoupper($currency ?: 'KES'),
        ];
    }

    public static function normalizeUpfrontPercent(mixed $percent): int
    {
        if ($percent === null || $percent === '') {
            return 100;
        }

        return max(1, min(100, (int) $percent));
    }

    public static function upfrontAmount(float $totalAmount, mixed $upfrontPercent): float
    {
        $percent = self::normalizeUpfrontPercent($upfrontPercent);

        return round($totalAmount * ($percent / 100), 2);
    }
}
