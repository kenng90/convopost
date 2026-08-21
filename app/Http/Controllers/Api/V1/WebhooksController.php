<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use App\Scopes\CompanyScope;
use App\Services\Api\PublicApiResponse;
use App\Services\Security\SafeRemoteUrl;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebhooksController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('public_api_company');
        $endpoints = WebhookEndpoint::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (WebhookEndpoint $endpoint) => $this->present($endpoint, false))
            ->values()
            ->all();

        return PublicApiResponse::success($endpoints);
    }

    public function store(Request $request, SafeRemoteUrl $safeRemoteUrl): JsonResponse
    {
        $request->validate([
            'url' => 'required|url|max:2048',
            'events' => 'nullable|array',
            'events.*' => 'string',
            'description' => 'nullable|string|max:255',
        ]);

        if (! $safeRemoteUrl->isPublicHttpUrl($request->url)) {
            throw new HttpResponseException(PublicApiResponse::error('invalid_request', 'Webhook URL must be a public HTTP URL.', 422));
        }

        $allowed = config('public-api.webhook.events', []);
        $events = $request->input('events', ['*']);

        foreach ($events as $event) {
            if ($event !== '*' && ! in_array($event, $allowed, true)) {
                throw new HttpResponseException(PublicApiResponse::error('invalid_request', 'Unknown event: '.$event, 422));
            }
        }

        $company = $request->attributes->get('public_api_company');
        $endpoint = WebhookEndpoint::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'url' => $request->url,
            'secret' => 'whsec_'.Str::random(40),
            'events' => $events,
            'is_active' => true,
            'description' => $request->input('description'),
        ]);

        Cache::forget('public-api:webhooks:'.$company->id);

        return PublicApiResponse::success($this->present($endpoint, true), 201);
    }

    public function destroy(Request $request, int $webhook): JsonResponse
    {
        $company = $request->attributes->get('public_api_company');
        $endpoint = WebhookEndpoint::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->find($webhook);

        if (! $endpoint) {
            throw new HttpResponseException(PublicApiResponse::error('not_found', 'Webhook not found', 404));
        }

        $endpoint->delete();
        Cache::forget('public-api:webhooks:'.$company->id);

        return PublicApiResponse::success(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(WebhookEndpoint $endpoint, bool $includeSecret): array
    {
        return [
            'id' => $endpoint->id,
            'url' => $endpoint->url,
            'events' => $endpoint->events ?? ['*'],
            'is_active' => $endpoint->is_active,
            'description' => $endpoint->description,
            'secret' => $includeSecret ? $endpoint->secret : null,
            'created_at' => optional($endpoint->created_at)?->toIso8601String(),
        ];
    }
}
