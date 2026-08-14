<?php

namespace App\Services\Outcomes;

use App\Models\Company;
use App\Models\Config;
use App\Services\Campaign\CampaignTriggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Contact;

/**
 * Normalize Shopify / WooCommerce commerce webhooks into campaign triggers + journey enrollment.
 */
class StoreCommerceWebhookService
{
    public function __construct(
        private readonly CampaignTriggerService $triggers,
        private readonly OutcomeJourneyEnroller $enroller,
    ) {
    }

    public function resolveCompanyByToken(string $token): ?Company
    {
        $config = Config::query()
            ->where('key', 'plain_token')
            ->where('value', $token)
            ->where('model_type', Company::class)
            ->orderByDesc('id')
            ->first();

        return $config ? Company::find($config->model_id) : null;
    }

    public function handleShopify(Company $company, Request $request): array
    {
        $topic = strtolower((string) $request->header('X-Shopify-Topic', ''));
        $payload = $request->all();

        return match (true) {
            str_contains($topic, 'checkouts/create'),
            str_contains($topic, 'checkouts/update'),
            str_contains($topic, 'carts/') => $this->handleAbandonedCheckout($company, $payload, 'shopify'),
            str_contains($topic, 'orders/create'),
            str_contains($topic, 'orders/paid') => $this->handleOrder($company, $payload, 'shopify'),
            str_contains($topic, 'fulfillments/') => $this->handleFulfillment($company, $payload, 'shopify'),
            default => ['handled' => false, 'event' => $topic, 'triggered' => 0],
        };
    }

    public function handleWooCommerce(Company $company, Request $request): array
    {
        $topic = strtolower((string) ($request->header('X-WC-Webhook-Topic') ?: $request->input('topic', '')));
        $payload = $request->all();

        return match (true) {
            str_contains($topic, 'order.created'),
            str_contains($topic, 'order.updated') => $this->handleOrder($company, $payload, 'woocommerce'),
            default => ['handled' => false, 'event' => $topic, 'triggered' => 0],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleAbandonedCheckout(Company $company, array $payload, string $source): array
    {
        $phone = $this->extractPhone($payload);
        $email = $payload['email'] ?? $payload['customer']['email'] ?? null;
        $name = trim(($payload['billing_address']['first_name'] ?? $payload['customer']['first_name'] ?? '').' '.($payload['billing_address']['last_name'] ?? $payload['customer']['last_name'] ?? ''));

        if (! $phone && $email) {
            $contact = Contact::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('email', $email)
                ->first();
            $phone = $contact?->phone;
        }

        if (! $phone) {
            Log::info('Store commerce webhook skipped — no phone', ['source' => $source, 'company_id' => $company->id]);

            return ['handled' => true, 'event' => 'cart.abandoned', 'triggered' => 0, 'enrolled' => false];
        }

        $data = [
            'phone' => $phone,
            'customer_phone' => $phone,
            'customer_name' => $name ?: ($payload['email'] ?? $phone),
            'source' => $source,
            'checkout_id' => $payload['id'] ?? $payload['token'] ?? null,
            'total_price' => $payload['total_price'] ?? $payload['total'] ?? null,
            'currency' => $payload['currency'] ?? $payload['presentment_currency'] ?? null,
        ];

        $triggered = $this->triggers->fire($company, CampaignTriggerService::EVENT_CART_ABANDONED, $data);
        $enrolled = $this->enroller->enrollCartAbandoned($company, $phone, $data['customer_name']);

        return [
            'handled' => true,
            'event' => CampaignTriggerService::EVENT_CART_ABANDONED,
            'triggered' => $triggered,
            'enrolled' => $enrolled,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleOrder(Company $company, array $payload, string $source): array
    {
        $phone = $this->extractPhone($payload);
        $name = trim(($payload['billing_address']['first_name'] ?? $payload['billing']['first_name'] ?? '').' '.($payload['billing_address']['last_name'] ?? $payload['billing']['last_name'] ?? ''));

        if (! $phone) {
            return ['handled' => true, 'event' => 'order.created', 'triggered' => 0];
        }

        $data = [
            'phone' => $phone,
            'customer_phone' => $phone,
            'customer_name' => $name ?: $phone,
            'source' => $source,
            'order_id' => $payload['id'] ?? null,
            'order_number' => $payload['order_number'] ?? $payload['name'] ?? null,
            'total_price' => $payload['total_price'] ?? $payload['total'] ?? null,
        ];

        $triggered = $this->triggers->fire($company, CampaignTriggerService::EVENT_ORDER_CREATED, $data);

        $contact = Contact::firstOrCreate(
            ['company_id' => $company->id, 'phone' => $phone],
            ['name' => $data['customer_name'], 'subscribed' => 1]
        );
        $this->enroller->markCartRecovered($company, $contact);

        return [
            'handled' => true,
            'event' => CampaignTriggerService::EVENT_ORDER_CREATED,
            'triggered' => $triggered,
            'recovered' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleFulfillment(Company $company, array $payload, string $source): array
    {
        $phone = $this->extractPhone($payload);
        if (! $phone) {
            return ['handled' => true, 'event' => 'fulfillment.shipped', 'triggered' => 0];
        }

        $triggered = $this->triggers->fire($company, CampaignTriggerService::EVENT_FULFILLMENT_SHIPPED, [
            'phone' => $phone,
            'customer_phone' => $phone,
            'source' => $source,
            'tracking_number' => $payload['tracking_number'] ?? null,
        ]);

        return [
            'handled' => true,
            'event' => CampaignTriggerService::EVENT_FULFILLMENT_SHIPPED,
            'triggered' => $triggered,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPhone(array $payload): ?string
    {
        $candidates = [
            $payload['phone'] ?? null,
            $payload['customer']['phone'] ?? null,
            $payload['billing_address']['phone'] ?? null,
            $payload['shipping_address']['phone'] ?? null,
            $payload['billing']['phone'] ?? null,
            $payload['shipping']['phone'] ?? null,
        ];

        foreach ($candidates as $phone) {
            if (is_string($phone) && trim($phone) !== '') {
                return preg_replace('/\s+/', '', $phone);
            }
        }

        return null;
    }
}
