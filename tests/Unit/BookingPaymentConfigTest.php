<?php

namespace Tests\Unit;

use Modules\Reminders\Support\BookingPaymentConfig;
use PHPUnit\Framework\TestCase;

class BookingPaymentConfigTest extends TestCase
{
    public function test_full_payment_when_percent_is_null_or_100(): void
    {
        $config = BookingPaymentConfig::build(true, 2000.0, null, 'kes');

        $this->assertTrue($config['payment_required']);
        $this->assertSame(2000.0, $config['payment_total_amount']);
        $this->assertSame(100, $config['payment_upfront_percent']);
        $this->assertSame(2000.0, $config['payment_amount']);
        $this->assertSame('KES', $config['payment_currency']);
    }

    public function test_upfront_percent_calculates_partial_charge(): void
    {
        $config = BookingPaymentConfig::build(true, 2000.0, 50, 'KES');

        $this->assertTrue($config['payment_required']);
        $this->assertSame(2000.0, $config['payment_total_amount']);
        $this->assertSame(50, $config['payment_upfront_percent']);
        $this->assertSame(1000.0, $config['payment_amount']);
    }

    public function test_percent_is_clamped_between_one_and_one_hundred(): void
    {
        $this->assertSame(1, BookingPaymentConfig::normalizeUpfrontPercent(0));
        $this->assertSame(100, BookingPaymentConfig::normalizeUpfrontPercent(150));
        $this->assertSame(25, BookingPaymentConfig::normalizeUpfrontPercent(25));
    }

    public function test_payment_not_required_when_total_is_zero(): void
    {
        $config = BookingPaymentConfig::build(true, 0, 50, 'KES');

        $this->assertFalse($config['payment_required']);
    }
}
