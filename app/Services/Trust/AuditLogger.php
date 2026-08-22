<?php

namespace App\Services\Trust;

use App\Models\Company;
use App\Models\PlatformAuditLog;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(Company $company, string $action, ?string $subjectType = null, ?int $subjectId = null, array $metadata = []): PlatformAuditLog
    {
        return PlatformAuditLog::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata ?: null,
        ]);
    }
}
