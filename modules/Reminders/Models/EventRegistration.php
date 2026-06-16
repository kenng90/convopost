<?php

namespace Modules\Reminders\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        $event = $this->event;
        $contact = $this->contact;

        if (! $event || ! $contact || ! $event->confirmation_campaign_id) {
            return;
        }

        $campaign = \Modules\Wpbox\Models\Campaign::find($event->confirmation_campaign_id);

        if (! $campaign) {
            return;
        }

        $occurrence = $this->occurrence;
        $start = $occurrence?->starts_at;

        $contact->extra_value = [
            'start_date' => $start?->toDateString(),
            'start_time' => $start?->toTimeString(),
            'start_date_time' => $start?->toDateTimeString(),
            'end_date' => $occurrence?->ends_at?->toDateString(),
            'end_time' => $occurrence?->ends_at?->toTimeString(),
            'end_date_time' => $occurrence?->ends_at?->toDateTimeString(),
            'external_id' => $this->external_id,
            'event_title' => $event->title,
        ];

        $request = new \Illuminate\Http\Request();
        $request->replace(['send_time' => now()->toDateTimeString()]);

        $message = $campaign->makeMessages($request, $contact);

        try {
            $message->extra = 'event_reg:'.$this->id;
            $message->save();
        } catch (\Throwable) {
        }
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
