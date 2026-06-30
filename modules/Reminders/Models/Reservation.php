<?php

namespace Modules\Reminders\Models;

use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

class Reservation extends Model
{
    use HasFactory;

    protected $table = 'rem_reservations';

    public $guarded = [];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'cancelled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'payment_amount' => 'decimal:2',
        'payment_total_amount' => 'decimal:2',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function appointmentStaffMember(): BelongsTo
    {
        return $this->belongsTo(AppointmentStaff::class, 'appointment_staff_id');
    }

    public function displayStatus(): string
    {
        if ($this->cancelled_at !== null || (int) $this->status === 2) {
            return 'cancelled';
        }

        if ($this->end_date?->isPast()) {
            return 'completed';
        }

        if ($this->start_date?->isFuture()) {
            return 'upcoming';
        }

        if ($this->start_date && $this->end_date && now()->between($this->start_date, $this->end_date)) {
            return 'in_progress';
        }

        return 'confirmed';
    }

    public function displayStatusLabel(): string
    {
        return match ($this->displayStatus()) {
            'cancelled' => __('Cancelled'),
            'completed' => __('Completed'),
            'upcoming' => __('Upcoming'),
            'in_progress' => __('In progress'),
            default => __('Confirmed'),
        };
    }

    public function displayStatusBadgeClass(): string
    {
        return match ($this->displayStatus()) {
            'cancelled' => 'badge-secondary',
            'completed' => 'badge-info',
            'upcoming' => 'badge-primary',
            'in_progress' => 'badge-warning',
            default => 'badge-success',
        };
    }

    public function formattedDuration(): ?string
    {
        if (! $this->duration_minutes) {
            return null;
        }

        return __(':minutes min', ['minutes' => $this->duration_minutes]);
    }

    /**
     * WhatsApp reminder messages scheduled for this reservation.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Message>
     */
    public function reminderMessages()
    {
        return Message::query()
            ->where('company_id', $this->company_id)
            ->where(function ($query) {
                $query->where('extra', (string) $this->id)
                    ->orWhere('extra', $this->id);
            })
            ->with('campaign')
            ->orderBy('created_at')
            ->get();
    }

    public function isActive(): bool
    {
        return (int) $this->status === 1 && $this->cancelled_at === null;
    }

    public function scopeFilterByDisplayStatus(Builder $query, ?string $status): Builder
    {
        if (! $status || $status === 'all') {
            return $query;
        }

        return match ($status) {
            'cancelled' => $query->where(function (Builder $builder) {
                $builder->whereNotNull('cancelled_at')->orWhere('status', 2);
            }),
            'completed' => $query->whereNull('cancelled_at')
                ->where('status', '!=', 2)
                ->where('end_date', '<', now()),
            'upcoming' => $query->whereNull('cancelled_at')
                ->where('status', '!=', 2)
                ->where('start_date', '>', now()),
            'in_progress' => $query->whereNull('cancelled_at')
                ->where('status', '!=', 2)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now()),
            'confirmed' => $query->whereNull('cancelled_at')
                ->where('status', 1)
                ->where(function (Builder $builder) {
                    $builder->where('start_date', '>', now())
                        ->orWhere(function (Builder $nested) {
                            $nested->where('start_date', '<=', now())
                                ->where('end_date', '>=', now());
                        });
                }),
            default => $query,
        };
    }

    public function makeMessages()
    {
        //Make the actual messages

        //Get all the Reminders for this reservation
        $reminders = Remineder::where('company_id', $this->company_id)
            ->where('status', 1)
            ->get();

        //Remove the reminders that are not for the same source as the reservation
        $reminders = $reminders->reject(function ($reminder) {
            return $reminder->source_id !== null && $reminder->source_id != $this->source_id;
        });

        //For each reminder, make the messages
        foreach ($reminders as $reminder) {
            $reminder->makeMessages($this);
        }

    }

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $company_id = session('company_id', null);
            if ($company_id) {
                $model->company_id = $company_id;
            }

        });

        //Handle after create
        static::created(function ($model) {
            //Make the actual messages
            $model->makeMessages();
        });
    }
}
