<?php

namespace App\Console\Commands;

use App\Services\VendorBrandingScrubber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScrubVendorBranding extends Command
{
    protected $signature = 'app:scrub-vendor-branding {--reset-templates : Replace email and SMS templates with neutral defaults}';

    protected $description = 'Remove legacy Mobidonia/Daniel branding from seeded demo data';

    public function handle(): int
    {
        $updated = 0;

        if ($this->option('reset-templates')) {
            $updated += $this->resetTemplateConfigs();
        } else {
            $updated += $this->scrubConfigs();
        }

        $updated += $this->scrubTable('companies', ['name', 'description', 'subdomain']);
        $updated += $this->scrubTable('contacts', ['name', 'email', 'last_message']);
        $updated += $this->scrubTable('messages', ['value', 'original_message']);
        $updated += $this->scrubTable('wa_campaings', ['variables']);

        $this->info("Updated {$updated} record(s).");

        return Command::SUCCESS;
    }

    private function resetTemplateConfigs(): int
    {
        $updated = 0;
        $templates = array_merge(
            VendorBrandingScrubber::emailTemplateConfigs(),
            VendorBrandingScrubber::smsTemplateConfigs()
        );

        foreach ($templates as $template) {
            $rows = DB::table('configs')
                ->where('key', $template['key'])
                ->where('model_type', 'App\\Models\\Company')
                ->update([
                    'value' => $template['value'],
                    'updated_at' => now(),
                ]);

            $updated += $rows;
        }

        return $updated;
    }

    private function scrubConfigs(): int
    {
        $updated = 0;

        DB::table('configs')
            ->orderBy('id')
            ->chunkById(100, function ($configs) use (&$updated) {
                foreach ($configs as $config) {
                    if (! VendorBrandingScrubber::containsVendorBranding($config->value)) {
                        continue;
                    }

                    DB::table('configs')
                        ->where('id', $config->id)
                        ->update([
                            'value' => VendorBrandingScrubber::scrub($config->value),
                            'updated_at' => now(),
                        ]);

                    $updated++;
                }
            });

        return $updated;
    }

    private function scrubTable(string $table, array $columns): int
    {
        $updated = 0;

        DB::table($table)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $columns, &$updated) {
                foreach ($rows as $row) {
                    $changes = [];

                    foreach ($columns as $column) {
                        $value = $row->{$column} ?? null;

                        if (! is_string($value) || ! VendorBrandingScrubber::containsVendorBranding($value)) {
                            continue;
                        }

                        $changes[$column] = VendorBrandingScrubber::scrub($value);
                    }

                    if ($changes === []) {
                        continue;
                    }

                    $changes['updated_at'] = now();

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update($changes);

                    $updated++;
                }
            });

        return $updated;
    }
}
