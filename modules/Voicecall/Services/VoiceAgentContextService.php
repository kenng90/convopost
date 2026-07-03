<?php

namespace Modules\Voicecall\Services;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Services\VoiceBooking\VoiceBookingContextService;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Models\Flowdocument;
use Modules\Voicecall\Models\VoicePhoneNumber;
use Modules\Whatsappcall\Services\VoiceAgentCapabilityBriefService;

class VoiceAgentContextService
{
    public function buildSystemContext(VoicePhoneNumber $line, Company $company): string
    {
        $parts = [];

        $greeting = trim($line->ai_greeting ?? '') ?: config('voicecall.default_greeting');
        $parts[] = 'Greeting: '.$greeting;

        if ($line->voice_flow_id) {
            $flow = Flow::withoutGlobalScopes()->where('id', $line->voice_flow_id)
                ->where('company_id', $company->id)
                ->first();
            if ($flow) {
                $parts[] = 'Voice flow: '.$flow->name;
                $docs = Flowdocument::where('flow_id', $flow->id)->limit(20)->pluck('content');
                foreach ($docs as $doc) {
                    if ($doc) {
                        $parts[] = 'Knowledge: '.mb_substr(strip_tags($doc), 0, 500);
                    }
                }
            }
        }

        foreach ($line->catalog_ids ?? [] as $catalogId) {
            $catalog = ListCatalog::withoutGlobalScopes()
                ->where('id', $catalogId)
                ->where('company_id', $company->id)
                ->first();
            if (! $catalog) {
                continue;
            }
            $items = collect($catalog->items ?? [])->take(30);
            $parts[] = 'Catalog: '.$catalog->name;
            foreach ($items as $item) {
                $title = $item['title'] ?? 'Item';
                $price = $item['price'] ?? '';
                $desc = mb_substr($item['description'] ?? '', 0, 120);
                $parts[] = "- {$title}".($price ? " ({$price})" : '').($desc ? ": {$desc}" : '');
            }
        }

        $booking = app(VoiceBookingContextService::class)->buildForCompany($company);
        if (! empty($booking['booking_context'])) {
            $parts[] = $booking['booking_context'];
        }

        $capabilityBrief = app(VoiceAgentCapabilityBriefService::class)->buildForCompany($company);
        if (! empty($capabilityBrief['instruction_brief'])) {
            $parts[] = $capabilityBrief['instruction_brief'];
        }
        if (! empty($capabilityBrief['mention_in_greeting']) && ! empty($capabilityBrief['spoken_brief'])) {
            $parts[] = 'Opening orientation for the caller: '.$capabilityBrief['spoken_brief'];
        }

        return implode("\n", $parts);
    }
}
