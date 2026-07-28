<?php

namespace App\Services\Campaign;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Modules\Wpbox\Models\Contact;

class CampaignContactUpsertService
{
    /**
     * @param  array<int, string>  $phones
     * @return array<string, Contact>
     */
    public function upsertPhones(Company $company, array $phones): array
    {
        $phones = array_values(array_unique(array_filter(array_map('strval', $phones))));

        if ($phones === []) {
            return [];
        }

        $existing = Contact::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('phone', $phones)
            ->get()
            ->keyBy('phone');

        $missing = array_values(array_diff($phones, $existing->keys()->all()));

        if ($missing !== []) {
            $now = now();
            $rows = array_map(fn (string $phone) => [
                'name' => $phone,
                'phone' => $phone,
                'company_id' => $company->id,
                'subscribed' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], $missing);

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('contacts')->insert($chunk);
            }

            $inserted = Contact::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereIn('phone', $missing)
                ->get()
                ->keyBy('phone');

            $existing = $existing->merge($inserted);
        }

        return $existing->all();
    }

    public function upsertPhone(Company $company, string $phone): Contact
    {
        return $this->upsertPhones($company, [$phone])[$phone];
    }

    public function upsertEmail(Company $company, string $email): Contact
    {
        return Contact::withoutGlobalScopes()->firstOrCreate(
            ['email' => $email, 'company_id' => $company->id],
            ['name' => $email, 'phone' => '', 'subscribed' => 1]
        );
    }
}
