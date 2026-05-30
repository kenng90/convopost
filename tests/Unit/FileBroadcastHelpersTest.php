<?php

namespace Tests\Unit;

use Modules\Wpbox\Http\Controllers\CampaignsController;
use ReflectionMethod;
use Tests\TestCase;

class FileBroadcastHelpersTest extends TestCase
{
    public function test_normalize_phone_from_excel_numeric_cell(): void
    {
        $phone = $this->invokeNormalizePhone(15551234567.0);

        $this->assertSame('15551234567', $phone);
    }

    public function test_normalize_phone_rejects_too_short_values(): void
    {
        $this->assertNull($this->invokeNormalizePhone('12345'));
    }

    public function test_resolve_phone_column_index_is_case_insensitive(): void
    {
        $index = $this->invokeResolvePhoneColumnIndex(['Name', 'Phone'], 'phone');

        $this->assertSame(1, $index);
    }

    private function invokeNormalizePhone(mixed $value): ?string
    {
        $method = new ReflectionMethod(CampaignsController::class, 'normalizePhoneFromFileCell');
        $method->setAccessible(true);

        return $method->invoke(new CampaignsController(), $value);
    }

    private function invokeResolvePhoneColumnIndex(array $headers, string $phoneColumn): int|false
    {
        $method = new ReflectionMethod(CampaignsController::class, 'resolvePhoneColumnIndex');
        $method->setAccessible(true);

        return $method->invoke(new CampaignsController(), $headers, $phoneColumn);
    }
}
