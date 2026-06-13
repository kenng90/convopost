<?php

namespace Modules\Contacts\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Contacts\Imports\ContactsImport;
use Modules\Contacts\Models\ContactImport;
use Throwable;

class ProcessContactImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public ContactImport $contactImport)
    {
    }

    public function handle(): void
    {
        $this->contactImport->refresh();

        if ($this->contactImport->isFinished()) {
            return;
        }

        session(['company_id' => $this->contactImport->company_id]);

        Excel::import(
            new ContactsImport($this->contactImport->id),
            $this->contactImport->file_path,
            $this->contactImport->disk
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Contact import failed', [
            'contact_import_id' => $this->contactImport->id,
            'message' => $exception->getMessage(),
        ]);

        $this->contactImport->update([
            'status' => ContactImport::STATUS_FAILED,
            'error_message' => $exception->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
