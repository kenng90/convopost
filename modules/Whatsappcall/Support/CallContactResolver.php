<?php

namespace Modules\Whatsappcall\Support;

use Modules\Wpbox\Models\Contact;

class CallContactResolver
{
    public static function findByPhone(int $companyId, ?string $phone): ?Contact
    {
        if (! $phone || $companyId <= 0) {
            return null;
        }

        $normalized = ltrim($phone, '+');

        return Contact::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($phone, $normalized) {
                $query->where('phone', $phone)
                    ->orWhere('phone', '+'.$normalized)
                    ->orWhere('phone', $normalized);
            })
            ->first();
    }
}
