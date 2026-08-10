<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Crypt;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;

class BookingManageTokenService
{
    private const TTL_DAYS = 30;

    public function makeReservationUrl(Company $company, Reservation $reservation): string
    {
        return route('reminders.booking.manage.reservation', [
            'subdomain' => $company->subdomain,
            'token' => $this->encode('r', (int) $company->id, (int) $reservation->id),
        ]);
    }

    public function makeRegistrationUrl(Company $company, EventRegistration $registration): string
    {
        return route('reminders.booking.manage.registration', [
            'subdomain' => $company->subdomain,
            'token' => $this->encode('e', (int) $company->id, (int) $registration->id),
        ]);
    }

    public function landingUrl(Company $company, string $type = 'appointments'): string
    {
        return route('reminders.booking.manage', [
            'subdomain' => $company->subdomain,
            'type' => $type === 'events' ? 'events' : 'appointments',
        ]);
    }

    /**
     * @return array{type: string, company_id: int, id: int}|null
     */
    public function decode(string $token): ?array
    {
        try {
            $json = Crypt::decryptString($this->fromUrlSafe($token));
            $data = json_decode($json, true);

            if (! is_array($data)) {
                return null;
            }

            $type = (string) ($data['t'] ?? '');
            $companyId = (int) ($data['c'] ?? 0);
            $id = (int) ($data['i'] ?? 0);
            $exp = (int) ($data['exp'] ?? 0);

            if (! in_array($type, ['r', 'e'], true) || $companyId <= 0 || $id <= 0) {
                return null;
            }

            if ($exp > 0 && $exp < time()) {
                return null;
            }

            return [
                'type' => $type,
                'company_id' => $companyId,
                'id' => $id,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function encode(string $type, int $companyId, int $id): string
    {
        $encrypted = Crypt::encryptString(json_encode([
            't' => $type,
            'c' => $companyId,
            'i' => $id,
            'exp' => now()->addDays(self::TTL_DAYS)->timestamp,
        ], JSON_THROW_ON_ERROR));

        return $this->toUrlSafe($encrypted);
    }

    private function toUrlSafe(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function fromUrlSafe(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
