<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\ListCatalog;
use App\Services\Catalog\CatalogItemRepository;
use App\Services\Catalog\CatalogUrlService;
use App\Services\Flowmaker\FlowRunLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Wpbox\Models\Message;

class CatalogSearch extends Node
{
    public function listenForReply($message, $data)
    {
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);
        if (! $contact) {
            return;
        }

        $extra = is_object($data) ? ($data->extra ?? null) : ($data['extra'] ?? null);
        $query = trim((string) (is_object($data) ? ($data->value ?? '') : ($data['value'] ?? '')));

        if (is_string($extra) && str_starts_with($extra, 'catalog_search_')) {
            $productId = $this->resolveProductIdFromExtra($extra);
            $matches = json_decode($contact->getContactStateValue($this->flow_id, 'catalog_search_matches') ?: '[]', true) ?: [];
            $selected = collect($matches)->first(fn ($item) => (string) ($item['id'] ?? '') === $productId);

            $contact->clearContactState($this->flow_id, 'current_node');

            if ($selected) {
                $contact->setContactState($this->flow_id, 'selected_product', json_encode($selected));
                $contact->setContactState($this->flow_id, 'catalog_search_query', $query);
                $next = $this->getNextNodeId('onMatch') ?: $this->getNextNodeId('onProductSelected');
                if ($next) {
                    $next->process($message, $data);
                }

                return;
            }

            $else = $this->getNextNodeId('else') ?: $this->getNextNodeId('onNoMatch');
            if ($else) {
                $else->process($message, $data);
            }

            return;
        }

        if ($query === '') {
            return;
        }

        $this->runSearch($contact, $query, $message, $data);
    }

    public function process($message, $data)
    {
        if ($this->isStartNode) {
            $this->listenForReply($message, $data);

            return ['success' => true];
        }

        if ($skip = $this->skipIfNotWhatsappChannel(
            $message,
            $data,
            __('Catalog search is available on WhatsApp only.'),
        )) {
            return $skip;
        }

        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);
        if (! $contact) {
            return ['success' => false];
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $prompt = (string) ($settings['searchPrompt'] ?? 'What are you looking for? Reply with a product name or keyword.');

        $contact->setContactState($this->flow_id, 'current_node', $this->id);
        $contact->sendMessage($prompt, false, false, 'TEXT');
        FlowRunLogger::log((int) $this->flow_id, (int) $contact->id, 'catalog_search_started', (string) $this->id);

        return ['success' => true, 'waiting' => true];
    }

    private function runSearch(Contact $contact, string $query, $message, $data): void
    {
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $catalogId = $settings['catalogId'] ?? null;
        $maxResults = max(1, min(10, (int) ($settings['maxResults'] ?? 5)));

        if (! $catalogId) {
            $contact->sendMessage(__('No catalog is configured for search.'), false, false, 'TEXT');

            return;
        }

        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);
        if (! $catalog) {
            $contact->sendMessage(__('Catalog not found.'), false, false, 'TEXT');

            return;
        }

        $items = app(CatalogItemRepository::class)->getItemsArray($catalog);
        $needle = mb_strtolower($query);
        $matches = array_values(array_filter($items, function (array $item) use ($needle) {
            $haystack = mb_strtolower(
                ($item['title'] ?? '').' '.($item['description'] ?? '').' '.($item['category'] ?? '').' '.implode(' ', $item['tags'] ?? [])
            );

            return $needle === '' || str_contains($haystack, $needle);
        }));
        $matches = array_slice($matches, 0, $maxResults);

        $contact->setContactState($this->flow_id, 'catalog_search_query', $query);
        $contact->setContactState($this->flow_id, 'catalog_search_matches', json_encode($matches));

        if ($matches === []) {
            $contact->clearContactState($this->flow_id, 'current_node');
            $contact->sendMessage(__('No products matched ":query". Try another keyword.', ['query' => $query]), false, false, 'TEXT');
            FlowRunLogger::log((int) $this->flow_id, (int) $contact->id, 'catalog_search_no_match', (string) $this->id, $query);
            $noMatch = $this->getNextNodeId('onNoMatch') ?: $this->getNextNodeId('else');
            if ($noMatch) {
                $noMatch->process($message, $data);
            }

            return;
        }

        if (count($matches) === 1) {
            $contact->clearContactState($this->flow_id, 'current_node');
            $contact->setContactState($this->flow_id, 'selected_product', json_encode($matches[0]));
            FlowRunLogger::log((int) $this->flow_id, (int) $contact->id, 'catalog_search_matched', (string) $this->id, $query);
            $next = $this->getNextNodeId('onMatch') ?: $this->getNextNodeId('onProductSelected');
            if ($next) {
                $next->process($message, $data);
            }

            return;
        }

        $this->sendInteractiveList($contact, $catalog, $matches, $settings);
        $contact->setContactState($this->flow_id, 'current_node', $this->id);
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     */
    private function sendInteractiveList(Contact $contact, ListCatalog $catalog, array $matches, array $settings): void
    {
        $company = \App\Models\Company::find($contact->company_id);
        $token = $company?->getConfig('whatsapp_permanent_access_token')
            ?: config('whatsapp.token');
        $phoneNumberId = $company?->getConfig('whatsapp_phone_number_id');

        if (! $token || ! $phoneNumberId) {
            $url = app(CatalogUrlService::class)->publicUrl($catalog);
            $contact->sendMessage(__('Results found. Browse here:').' '.$url, false, false, 'TEXT');

            return;
        }

        $rows = [];
        foreach ($matches as $item) {
            $id = (string) ($item['id'] ?? '');
            $rows[] = [
                'id' => 'catalog_search_'.$id.'_id'.$this->id.'_flow'.$this->flow_id,
                'title' => mb_substr((string) ($item['title'] ?? 'Item'), 0, 24),
                'description' => mb_substr((string) ($item['description'] ?? ($item['price'] ?? '')), 0, 72),
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $contact->phone,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => ['type' => 'text', 'text' => mb_substr((string) ($settings['header'] ?? 'Search results'), 0, 60)],
                'body' => ['text' => __('Pick a product to continue:')],
                'action' => [
                    'button' => 'View',
                    'sections' => [[
                        'title' => mb_substr($catalog->name, 0, 24),
                        'rows' => $rows,
                    ]],
                ],
            ],
        ];

        try {
            Http::withToken($token)->post(
                'https://graph.facebook.com/v20.0/'.$phoneNumberId.'/messages',
                $payload
            );
            Message::create([
                'contact_id' => $contact->id,
                'company_id' => $contact->company_id,
                'value' => (string) ($settings['header'] ?? 'Search results'),
                'is_message_by_contact' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CatalogSearch list send failed', ['error' => $e->getMessage()]);
            $url = app(CatalogUrlService::class)->publicUrl($catalog);
            $contact->sendMessage(__('Results found. Browse here:').' '.$url, false, false, 'TEXT');
        }
    }

    private function resolveProductIdFromExtra(string $extra): string
    {
        if (preg_match('/^catalog_search_(.+)_id.+_flow\d+$/', $extra, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
