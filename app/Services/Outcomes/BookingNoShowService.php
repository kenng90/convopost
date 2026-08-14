<?php

namespace App\Services\Outcomes;

use App\Models\Company;
use Illuminate\Support\Facades\Schema;
use Modules\Reminders\Models\Reservation;
use Modules\Wpbox\Models\Contact;

class BookingNoShowService
{
    public function __construct(
        private readonly OutcomeJourneyEnroller $enroller,
    ) {
    }

    /**
     * Find completed appointment windows and move contacts to No-show when playbook is installed.
     *
     * @return array{processed: int, moved: int}
     */
    public function process(?Company $onlyCompany = null, int $graceMinutes = 60): array
    {
        if (! class_exists(Reservation::class) || ! Schema::hasTable('rem_reservations')) {
            return ['processed' => 0, 'moved' => 0];
        }

        if (! Schema::hasColumn('rem_reservations', 'outcome_no_show_processed_at')) {
            return $this->processWithoutColumn($onlyCompany, $graceMinutes);
        }

        $query = Reservation::withoutGlobalScopes()
            ->whereNull('cancelled_at')
            ->where('status', '!=', 2)
            ->whereNull('outcome_no_show_processed_at')
            ->where('end_date', '<', now()->subMinutes($graceMinutes))
            ->where('end_date', '>=', now()->subDays(2));

        if ($onlyCompany) {
            $query->where('company_id', $onlyCompany->id);
        }

        $processed = 0;
        $moved = 0;

        $query->orderBy('id')->chunkById(100, function ($reservations) use (&$processed, &$moved) {
            foreach ($reservations as $reservation) {
                $processed++;
                $company = Company::find($reservation->company_id);
                if (! $company || $company->getConfig('outcome_booking_convert_installed', 'no') !== 'yes') {
                    $reservation->forceFill(['outcome_no_show_processed_at' => now()])->saveQuietly();

                    continue;
                }

                $contact = $this->resolveContact($reservation);
                if ($contact && $this->enroller->markBookingNoShow($company, $contact)) {
                    $moved++;
                }

                $reservation->forceFill(['outcome_no_show_processed_at' => now()])->saveQuietly();
            }
        });

        return ['processed' => $processed, 'moved' => $moved];
    }

    /**
     * @return array{processed: int, moved: int}
     */
    private function processWithoutColumn(?Company $onlyCompany, int $graceMinutes): array
    {
        $query = Reservation::withoutGlobalScopes()
            ->whereNull('cancelled_at')
            ->where('status', '!=', 2)
            ->where('end_date', '<', now()->subMinutes($graceMinutes))
            ->where('end_date', '>=', now()->subDay());

        if ($onlyCompany) {
            $query->where('company_id', $onlyCompany->id);
        }

        $processed = 0;
        $moved = 0;

        foreach ($query->limit(200)->get() as $reservation) {
            $processed++;
            $company = Company::find($reservation->company_id);
            if (! $company || $company->getConfig('outcome_booking_convert_installed', 'no') !== 'yes') {
                continue;
            }

            $markerKey = 'outcome_no_show_reservation_'.$reservation->id;
            if ($company->getConfig($markerKey, 'no') === 'yes') {
                continue;
            }

            $contact = $this->resolveContact($reservation);
            if ($contact && $this->enroller->markBookingNoShow($company, $contact)) {
                $moved++;
            }

            $company->setConfig($markerKey, 'yes');
        }

        return ['processed' => $processed, 'moved' => $moved];
    }

    private function resolveContact(Reservation $reservation): ?Contact
    {
        if ($reservation->contact_id) {
            $contact = Contact::withoutGlobalScopes()->find($reservation->contact_id);
            if ($contact) {
                return $contact;
            }
        }

        $phone = $reservation->phone ?? null;
        if (! $phone) {
            return null;
        }

        return Contact::firstOrCreate(
            ['company_id' => $reservation->company_id, 'phone' => $phone],
            ['name' => $reservation->name ?? $phone, 'subscribed' => 1]
        );
    }
}
