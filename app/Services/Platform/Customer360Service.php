<?php

namespace App\Services\Platform;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Modules\Invoice\Models\Invoice;
use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyStage;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

class Customer360Service
{
    public function forContact(Company $company, Contact $contact): array
    {
        return [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'avatar' => $contact->avatar,
                'resolved_chat' => (bool) $contact->resolved_chat,
                'custom_fields' => $contact->fields?->map(fn ($f) => [
                    'name' => $f->name,
                    'value' => $f->pivot->value ?? '',
                ])->values() ?? [],
            ],
            'journeys' => $this->journeySummary($contact),
            'bookings' => $this->upcomingBookings($company, $contact),
            'orders' => $this->recentInvoices($company, $contact),
            'campaigns' => $this->campaignHistory($company, $contact),
            'conversation_summary' => $this->summarizeRecentMessages($contact),
        ];
    }

    private function journeySummary(Contact $contact): array
    {
        if (! class_exists(Journey::class)) {
            return [];
        }

        $journeys = Journey::with('stages')->get();

        $contactStageIds = DB::table('journey_stage_contacts')
            ->where('contact_id', $contact->id)
            ->pluck('stage_id');

        $stagesByJourney = JourneyStage::whereIn('id', $contactStageIds)
            ->get()
            ->keyBy('journey_id');

        return $journeys->map(function (Journey $journey) use ($stagesByJourney) {
            $stage = $stagesByJourney->get($journey->id);

            return [
                'id' => $journey->id,
                'name' => $journey->name,
                'current_stage' => $stage?->name,
                'in_journey' => $stage !== null,
            ];
        })->values()->all();
    }

    private function upcomingBookings(Company $company, Contact $contact): array
    {
        if (! DB::getSchemaBuilder()->hasTable('reservations')) {
            return [];
        }

        return DB::table('reservations')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($contact) {
                $q->where('contact_id', $contact->id)
                    ->orWhere('phone', $contact->phone);
            })
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->limit(3)
            ->get(['id', 'start_time', 'status', 'service_name'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'start_time' => $r->start_time,
                'status' => $r->status,
                'service_name' => $r->service_name ?? __('Appointment'),
            ])
            ->all();
    }

    private function recentInvoices(Company $company, Contact $contact): array
    {
        if (! class_exists(Invoice::class)) {
            return [];
        }

        return Invoice::where('company_id', $company->id)
            ->where('customer_phone', $contact->phone)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'invoice_number' => $invoice->invoice_number,
                'amount' => (float) $invoice->amount,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
                'public_url' => route('invoice.public.show', $invoice->public_uuid),
                'created_at' => $invoice->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    private function campaignHistory(Company $company, Contact $contact): array
    {
        return Campaign::where('company_id', $company->id)
            ->where('contact_id', $contact->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'status', 'created_at', 'delivered_to', 'read_by'])
            ->map(fn (Campaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'status' => $c->status,
                'delivered' => $c->delivered_to,
                'read' => $c->read_by,
                'created_at' => $c->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    private function summarizeRecentMessages(Contact $contact): string
    {
        $messages = Message::where('contact_id', $contact->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->reverse()
            ->values();

        if ($messages->isEmpty()) {
            return __('No recent messages.');
        }

        $lines = $messages->map(function (Message $message) {
            $speaker = $message->is_message_by_contact ? __('Customer') : __('Team');

            return $speaker.': '.mb_substr(strip_tags((string) $message->value), 0, 120);
        });

        return $lines->implode("\n");
    }
}
