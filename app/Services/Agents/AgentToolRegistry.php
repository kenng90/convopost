<?php

namespace App\Services\Agents;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Services\Platform\Customer360Service;
use Modules\Invoice\Models\Invoice;
use Modules\Journies\Models\JourneyStage;
use Modules\Journies\Services\JourneyContactService;
use Modules\Knowledge\Models\KnowledgeArticle;
use Modules\Reminders\Models\Reservation;
use Modules\Wpbox\Models\Contact;

class AgentToolRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function openRouterTools(): array
    {
        return [
            $this->functionTool('search_knowledge', 'Search the company knowledge base for an answer.', [
                'query' => ['type' => 'string', 'description' => 'Search query'],
            ], ['query']),
            $this->functionTool('search_catalog', 'Search product or listing catalog items.', [
                'query' => ['type' => 'string', 'description' => 'Product or listing search'],
            ], ['query']),
            $this->functionTool('lookup_customer', 'Load Customer 360 context for the current contact.', []),
            $this->functionTool('create_invoice', 'Create a draft invoice for this customer.', [
                'amount' => ['type' => 'number'],
                'description' => ['type' => 'string'],
            ], ['amount']),
            $this->functionTool('move_journey_stage', 'Move the contact into a journey stage by stage id.', [
                'stage_id' => ['type' => 'integer'],
            ], ['stage_id']),
            $this->functionTool('list_bookings', 'List upcoming bookings for this customer.'),
            $this->functionTool('handoff_to_human', 'Stop the AI bot and assign a human agent.', [
                'reason' => ['type' => 'string'],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(Company $company, Contact $contact, string $name, array $arguments = []): array
    {
        return match ($name) {
            'search_knowledge' => $this->searchKnowledge($company, (string) ($arguments['query'] ?? '')),
            'search_catalog' => $this->searchCatalog($company, (string) ($arguments['query'] ?? '')),
            'lookup_customer' => $this->lookupCustomer($company, $contact),
            'create_invoice' => $this->createInvoice($company, $contact, $arguments),
            'move_journey_stage' => $this->moveJourney($company, $contact, (int) ($arguments['stage_id'] ?? 0)),
            'list_bookings' => $this->listBookings($company, $contact),
            'handoff_to_human' => app(AgentHandoffService::class)->handoff(
                $company,
                $contact,
                (string) ($arguments['reason'] ?? 'Customer requested a human')
            ),
            default => ['ok' => false, 'error' => 'Unknown tool'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function searchKnowledge(Company $company, string $query): array
    {
        if (! class_exists(KnowledgeArticle::class) || trim($query) === '') {
            return ['ok' => true, 'articles' => []];
        }

        $articles = KnowledgeArticle::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', '%'.$query.'%')
                    ->orWhere('content', 'like', '%'.$query.'%');
            })
            ->limit(3)
            ->get(['id', 'title', 'content']);

        return [
            'ok' => true,
            'articles' => $articles->map(fn ($article) => [
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => \Illuminate\Support\Str::limit(strip_tags((string) $article->content), 240),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function searchCatalog(Company $company, string $query): array
    {
        $catalogs = ListCatalog::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNull('parent_id')
            ->get();

        $needle = mb_strtolower(trim($query));
        $matches = [];

        foreach ($catalogs as $catalog) {
            foreach ($catalog->items ?? [] as $item) {
                $title = (string) ($item['title'] ?? $item['name'] ?? '');
                $hay = mb_strtolower($title.' '.($item['description'] ?? ''));
                if ($needle === '' || str_contains($hay, $needle)) {
                    $matches[] = [
                        'catalog' => $catalog->name,
                        'title' => $title,
                        'price' => $item['price'] ?? null,
                    ];
                }
                if (count($matches) >= 5) {
                    break 2;
                }
            }
        }

        return ['ok' => true, 'items' => $matches];
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupCustomer(Company $company, Contact $contact): array
    {
        return [
            'ok' => true,
            'customer' => app(Customer360Service::class)->forContact($company, $contact),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function createInvoice(Company $company, Contact $contact, array $arguments): array
    {
        if (! class_exists(Invoice::class)) {
            return ['ok' => false, 'error' => 'Invoices are not available'];
        }

        $amount = (float) ($arguments['amount'] ?? 0);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Amount must be greater than zero'];
        }

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => 'AI-'.now()->format('ymdHis'),
            'customer_name' => $contact->name ?: $contact->phone,
            'customer_phone' => $contact->phone,
            'customer_email' => $contact->email,
            'amount' => $amount,
            'currency' => $company->currency ?: 'KES',
            'status' => 'sent',
            'description' => (string) ($arguments['description'] ?? 'Invoice from WhatsApp agent'),
            'items' => [[
                'title' => (string) ($arguments['description'] ?? 'Service'),
                'quantity' => 1,
                'total' => $amount,
            ]],
            'notes' => ['source' => 'action_agent'],
            'sent_at' => now(),
        ]);

        return [
            'ok' => true,
            'invoice_id' => $invoice->id,
            'public_uuid' => $invoice->public_uuid,
            'amount' => $amount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function moveJourney(Company $company, Contact $contact, int $stageId): array
    {
        if ($stageId <= 0 || ! class_exists(JourneyStage::class)) {
            return ['ok' => false, 'error' => 'Invalid journey stage'];
        }

        $stage = JourneyStage::withoutGlobalScopes()
            ->where('id', $stageId)
            ->whereHas('journey', fn ($q) => $q->where('company_id', $company->id))
            ->first();

        if (! $stage) {
            return ['ok' => false, 'error' => 'Stage not found'];
        }

        $result = app(JourneyContactService::class)->moveContactToStage(
            $contact,
            $stage,
            'action_agent',
            null,
            true,
            true
        );

        return ['ok' => (bool) ($result['success'] ?? false), 'result' => $result];
    }

    /**
     * @return array<string, mixed>
     */
    public function listBookings(Company $company, Contact $contact): array
    {
        if (! class_exists(Reservation::class)) {
            return ['ok' => true, 'bookings' => []];
        }

        $bookings = Reservation::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('contact_id', $contact->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return [
            'ok' => true,
            'bookings' => $bookings->map(fn ($booking) => [
                'id' => $booking->id,
                'status' => $booking->status,
                'start' => optional($booking->start_date)->toDateTimeString(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  array<int, string>  $required
     * @return array<string, mixed>
     */
    private function functionTool(string $name, string $description, array $properties = [], array $required = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                ],
            ],
        ];
    }
}
