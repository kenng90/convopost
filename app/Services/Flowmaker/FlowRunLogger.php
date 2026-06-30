<?php

namespace App\Services\Flowmaker;

use Modules\Flowmaker\Models\FlowRunLog;

class FlowRunLogger
{
    public static function log(int $flowId, ?int $contactId, string $event, ?string $nodeId = null, ?string $detail = null): void
    {
        try {
            FlowRunLog::create([
                'flow_id' => $flowId,
                'contact_id' => $contactId,
                'node_id' => $nodeId,
                'event' => $event,
                'detail' => $detail ? mb_substr($detail, 0, 2000) : null,
            ]);
        } catch (\Throwable) {
            // Logging must never break flow execution.
        }
    }
}
