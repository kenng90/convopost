<?php

namespace Modules\Contacts\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Contacts\Models\ContactImport;
use Modules\Contacts\Support\ContactsImportGroupAssigner;
use Throwable;

class AssignContactImportGroupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public ContactImport $contactImport)
    {
    }

    public function handle(): void
    {
        if (! $this->contactImport->group_id) {
            return;
        }

        session(['company_id' => $this->contactImport->company_id]);

        ContactsImportGroupAssigner::assign($this->contactImport->fresh());
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Contact import group assignment failed', [
            'contact_import_id' => $this->contactImport->id,
            'message' => $exception->getMessage(),
        ]);

        $warnings = trim(($this->contactImport->warnings ?? '').' '.__('Contacts imported, but group assignment failed.'));

        $this->contactImport->update([
            'warnings' => $warnings,
        ]);
    }
}
