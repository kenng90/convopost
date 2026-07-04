<?php

namespace Modules\Reminders\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Reminders\Services\BookingMessageContextService;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

class EventRegistration extends Model
{
    use HasFactory;

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_WAITLISTED = 'waitlisted';

    protected $table = 'rem_event_registrations';

    public $guarded = [];

    protected $casts = [
        'party_size' => 'integer',
        'registered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'payment_amount' => 'decimal:2',
        'payment_total_amount' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_CONFIRMED && $this->cancelled_at === null;
    }

    public function displayStatus(): string
    {
        if ($this->status === self::STATUS_CANCELLED || $this->cancelled_at !== null) {
            return 'cancelled';
        }

        if ($this->status === self::STATUS_WAITLISTED) {
            return 'waitlisted';
        }

        $startsAt = $this->occurrence?->starts_at;

        if ($startsAt && $startsAt->isPast()) {
            return 'completed';
        }

        return 'confirmed';
    }

    public function displayStatusLabel(): string
    {
        return match ($this->displayStatus()) {
            'cancelled' => __('Cancelled'),
            'waitlisted' => __('Waitlisted'),
            'completed' => __('Completed'),
            default => __('Confirmed'),
        };
    }

    public function displayStatusBadgeClass(): string
    {
        return match ($this->displayStatus()) {
            'cancelled' => 'badge-secondary',
            'waitlisted' => 'badge-warning',
            'completed' => 'badge-info',
            default => 'badge-success',
        };
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Message>
     */
    public function reminderMessages()
    {
        return Message::query()
            ->where('company_id', $this->company_id)
            ->where(function ($query) {
                $query->where('extra', 'event_reg:'.$this->id)
                    ->orWhere('extra', (string) $this->id);
            })
            ->with('campaign')
            ->orderBy('created_at')
            ->get();
    }

    public function scheduleReminderMessages(): void
    {
        $reminders = Remineder::query()
            ->where('company_id', $this->company_id)
            ->where('status', 1)
            ->get()
            ->reject(function (Remineder $reminder) {
                if ($reminder->event_id !== null) {
                    return (int) $reminder->event_id !== (int) $this->event_id;
                }

                if ($reminder->source_id !== null) {
                    return true;
                }

                return false;
            });

        foreach ($reminders as $reminder) {
            $reminder->makeEventRegistrationMessages($this);
        }
    }

    public function sendConfirmationMessage(): void
    {
        app(BookingMessageContextService::class)->sendEventConfirmation($this);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model) {
            if (session('company_id') && ! $model->company_id) {
                $model->company_id = session('company_id');
            }

            if (! $model->registered_at) {
                $model->registered_at = now();
            }
        });

        static::created(function (self $model) {
            $model->load(['event', 'occurrence', 'contact']);
            $model->sendConfirmationMessage();
            $model->scheduleReminderMessages();
        });
    }
}
