<?php

namespace App\Services\WhatsappFlows;

use App\Models\WhatsappFlow;

class SequentialScreenDataExchangeRouter
{
    public function __construct(
        private readonly WhatsappFlowScreenInitService $screenInitService,
    ) {
    }

    /**
     * Advance to the next screen, or complete on the last screen.
     *
     * @param  array<string, mixed>  $data
     * @return array{screen: string, data: object|array<string, mixed>}
     */
    public function route(?WhatsappFlow $flow, string $currentScreenId, array $data, ?string $flowToken = null): array
    {
        $nextScreenId = $this->nextScreenId($flow, $currentScreenId);

        if ($nextScreenId !== null) {
            return [
                'screen' => $nextScreenId,
                'data' => (object) $this->screenInitService->initDataForScreen($flow, $nextScreenId),
            ];
        }

        $responseParams = array_merge(
            ['flow_token' => $flowToken ?? 'unused'],
            $this->userSubmittedData($data),
        );

        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => $responseParams,
                ],
            ],
        ];
    }

    public function nextScreenId(?WhatsappFlow $flow, string $currentScreenId): ?string
    {
        if (! $flow) {
            return null;
        }

        $screens = $flow->flow_json['screens'] ?? [];
        if (! is_array($screens) || $screens === []) {
            return null;
        }

        $ids = collect($screens)->pluck('id')->filter()->values();
        $index = $ids->search($currentScreenId);

        if ($index === false) {
            return null;
        }

        $next = $ids->get($index + 1);

        return is_string($next) && $next !== '' ? $next : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function userSubmittedData(array $data): array
    {
        return array_filter(
            $data,
            fn ($key) => ! in_array($key, ['url', 'error'], true),
            ARRAY_FILTER_USE_KEY
        );
    }
}
