<?php

namespace Modules\Reminders\Services;

use Illuminate\Support\Facades\DB;
use Modules\Reminders\Models\Remineder;
use Modules\Reminders\Models\Source;

class SourceArchiveService
{
    public function archive(Source $source): void
    {
        DB::transaction(function () use ($source) {
            Remineder::query()
                ->where('source_id', $source->id)
                ->delete();

            $source->update(['is_bookable' => false]);
            $source->delete();
        });
    }
}
