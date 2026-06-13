<?php

namespace Modules\Contacts\Models;

use App\Models\Company;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Contacts\Jobs\AssignContactImportGroupJob;

class ContactImport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'contact_imports';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    public function isFinalizing(): bool
    {
        return $this->status === self::STATUS_PROCESSING
            && $this->total_rows > 0
            && $this->processed_rows >= $this->total_rows;
    }

    public function displayStatus(): string
    {
        if ($this->isFinalizing()) {
            return $this->group_id
                ? __('Assigning to group')
                : __('Finalizing');
        }

        return ucfirst($this->status);
    }

    public function progressPercent(): int
    {
        if ($this->total_rows === 0) {
            return $this->status === self::STATUS_COMPLETED ? 100 : 0;
        }

        return min(100, (int) round(($this->processed_rows / $this->total_rows) * 100));
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function isStalePending(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->created_at !== null
            && $this->created_at->diffInMinutes(now()) >= 2;
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    public function toStatusPayload(): array
    {
        $this->finalizeIfAllRowsProcessed();

        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'status' => $this->status,
            'display_status' => $this->displayStatus(),
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'progress_percent' => $this->progressPercent(),
            'created_count' => $this->created_count,
            'updated_count' => $this->updated_count,
            'skipped_count' => $this->skipped_count,
            'is_finished' => $this->isFinished(),
            'is_finalizing' => $this->isFinalizing(),
            'is_stale_pending' => $this->isStalePending(),
            'error_message' => $this->error_message,
            'warnings' => $this->warnings,
            'status_url' => route('contacts.import.show', $this),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public function finalizeIfAllRowsProcessed(): void
    {
        if ($this->status !== self::STATUS_PROCESSING) {
            return;
        }

        if ($this->total_rows === 0 || $this->processed_rows < $this->total_rows) {
            return;
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'finished_at' => $this->finished_at ?? now(),
            'processed_rows' => $this->total_rows,
        ]);

        if ($this->group_id) {
            AssignContactImportGroupJob::dispatch($this);
        }
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }
}
