<?php

namespace App\Enums;

enum CollectionStatus: string
{
    case Draft = 'draft';
    case Due = 'due';
    case Requested = 'requested';
    case PendingPin = 'pending_pin';
    case Paid = 'paid';
    case Partial = 'partial';
    case Failed = 'failed';
    case Chasing = 'chasing';
    case Unmatched = 'unmatched';
    case Fulfilled = 'fulfilled';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function open(): array
    {
        return [
            self::Due->value,
            self::Requested->value,
            self::PendingPin->value,
            self::Partial->value,
            self::Failed->value,
            self::Chasing->value,
            self::Unmatched->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function terminal(): array
    {
        return [
            self::Paid->value,
            self::Fulfilled->value,
            self::Closed->value,
            self::Cancelled->value,
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->value, self::open(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, self::terminal(), true);
    }
}
