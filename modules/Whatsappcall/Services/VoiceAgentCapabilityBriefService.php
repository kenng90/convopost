<?php

namespace Modules\Whatsappcall\Services;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Services\VoiceBooking\VoiceBookingSettingsService;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\EventCatalogService;

class VoiceAgentCapabilityBriefService
{
    public function __construct(
        protected VoiceBookingSettingsService $bookingSettings,
        protected BookingCatalogService $bookingCatalog,
        protected EventCatalogService $eventCatalog,
    ) {
    }

    /**
     * Auto-derived capability brief for voice AI instructions and optional spoken greeting.
     *
     * @return array{
     *     categories: array<int, string>,
     *     examples: array<int, string>,
     *     instruction_brief: string,
     *     spoken_brief: string,
     *     mention_in_greeting: bool
     * }
     */
    public function buildForCompany(Company $company): array
    {
        $categories = [];
        $examples = [];

        $catalogIds = json_decode($company->getConfig('whatsapp_ai_catalog_ids', '[]'), true) ?: [];
        $productExamples = $this->catalogProductExamples($company, $catalogIds, 3);
        if ($productExamples !== []) {
            $categories[] = 'products and orders';
            $examples = array_merge($examples, $productExamples);
        }

        if ($this->bookingSettings->appointmentsEnabled($company)) {
            $services = $this->bookingCatalog->bookableServicesForCompany($company);
            if ($services !== []) {
                $categories[] = 'booking appointments';
                foreach (array_slice($services, 0, 2) as $service) {
                    $name = trim((string) ($service['name'] ?? ''));
                    if ($name !== '' && ! in_array($name, $examples, true)) {
                        $examples[] = $name;
                    }
                }
            }
        }

        if ($this->bookingSettings->eventsEnabled($company)) {
            $occurrences = $this->eventCatalog->upcomingOccurrencesForCompany($company, 2);
            if ($occurrences !== []) {
                $categories[] = 'event registration';
                foreach ($occurrences as $occurrence) {
                    $title = trim((string) ($occurrence['event_title'] ?? ''));
                    if ($title !== '' && ! in_array($title, $examples, true)) {
                        $examples[] = $title;
                    }
                }
            }
        }

        $flowId = (int) $company->getConfig('whatsapp_ai_flow_id', 0);
        if ($flowId > 0) {
            $categories[] = 'general questions about the business';
        }

        $categories = array_values(array_unique($categories));
        $examples = array_values(array_slice(array_unique($examples), 0, 4));

        if ($categories === []) {
            $categories[] = 'general questions';
        }

        $mentionInGreeting = filter_var(
            $company->getConfig('whatsapp_ai_mention_capabilities_in_greeting', true),
            FILTER_VALIDATE_BOOLEAN
        );

        $spokenBrief = $this->buildSpokenBrief($categories, $examples);
        $instructionBrief = $this->buildInstructionBrief($categories, $examples, $mentionInGreeting);

        return [
            'categories' => $categories,
            'examples' => $examples,
            'instruction_brief' => $instructionBrief,
            'spoken_brief' => $spokenBrief,
            'mention_in_greeting' => $mentionInGreeting,
        ];
    }

    /**
     * @param  array<int, int|string>  $catalogIds
     * @return array<int, string>
     */
    private function catalogProductExamples(Company $company, array $catalogIds, int $limit): array
    {
        $names = [];

        foreach ($catalogIds as $catalogId) {
            $catalog = ListCatalog::withoutGlobalScopes()
                ->where('id', (int) $catalogId)
                ->where('company_id', $company->id)
                ->first();

            if (! $catalog || ! is_array($catalog->items)) {
                continue;
            }

            foreach ($catalog->items as $item) {
                $title = trim((string) ($item['title'] ?? ''));
                if ($title === '' || in_array($title, $names, true)) {
                    continue;
                }
                $names[] = $title;
                if (count($names) >= $limit) {
                    return $names;
                }
            }
        }

        return $names;
    }

    /**
     * @param  array<int, string>  $categories
     * @param  array<int, string>  $examples
     */
    private function buildSpokenBrief(array $categories, array $examples): string
    {
        $categoryPhrase = $this->joinNatural($categories);
        $sentence = 'I can help with '.$categoryPhrase.'.';

        if ($examples !== []) {
            $sentence .= ' For example: '.$this->joinNatural($examples).'.';
        }

        $sentence .= ' What can I help you with today?';

        return $sentence;
    }

    /**
     * @param  array<int, string>  $categories
     * @param  array<int, string>  $examples
     */
    private function buildInstructionBrief(array $categories, array $examples, bool $mentionInGreeting): string
    {
        $lines = [
            '## What you can assist with (capability brief)',
            'You help callers with: '.$this->joinNatural($categories).'.',
        ];

        if ($examples !== []) {
            $lines[] = 'Example offerings to mention if relevant: '.$this->joinNatural($examples).'.';
        }

        $lines[] = 'Do not invent products or services that are not listed in your knowledge or tools.';
        $lines[] = 'Do not read a long catalog list unless the caller asks for options.';

        if ($mentionInGreeting) {
            $lines[] = 'In your opening greeting, briefly orient the caller using the spoken capability brief (categories and at most a few examples), then ask how you can help.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $parts
     */
    private function joinNatural(array $parts): string
    {
        $parts = array_values(array_filter(array_map('trim', $parts)));
        $count = count($parts);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $parts[0];
        }

        if ($count === 2) {
            return $parts[0].' and '.$parts[1];
        }

        $last = array_pop($parts);

        return implode(', ', $parts).', and '.$last;
    }
}
