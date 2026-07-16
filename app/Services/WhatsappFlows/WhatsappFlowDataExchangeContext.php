<?php

namespace App\Services\WhatsappFlows;

use App\Models\Company;
use App\Models\WhatsappFlow;

class WhatsappFlowDataExchangeContext
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly ?WhatsappFlow $flow,
        public readonly ?Company $company,
        public readonly string $screenId,
        public readonly ?string $endpointTemplate,
        public readonly array $data,
        public readonly ?string $flowToken = null,
    ) {
    }

    public static function fromExchange(
        ?WhatsappFlow $flow,
        ?Company $company,
        string $screenId,
        ?string $endpointTemplate,
        array $data,
        ?string $flowToken = null,
    ): self {
        return new self($flow, $company, $screenId, $endpointTemplate, $data, $flowToken);
    }

    public static function forInit(
        ?WhatsappFlow $flow,
        ?Company $company,
        string $screenId,
        ?string $endpointTemplate,
        ?string $flowToken = null,
    ): self {
        return new self($flow, $company, $screenId, $endpointTemplate, [], $flowToken);
    }
}
