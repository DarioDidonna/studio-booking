<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Confirmed => __('Confirmed'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
            self::NoShow => __('No-show'),
        };
    }

    /**
     * The Flux badge colour for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Confirmed => 'green',
            self::Completed => 'zinc',
            self::Cancelled => 'red',
            self::NoShow => 'orange',
        };
    }

    /**
     * Get the statuses this status may move to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled, self::NoShow],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Determine whether a booking in this status still occupies its time slot.
     */
    public function occupiesSlot(): bool
    {
        return in_array($this, self::slotOccupyingCases(), true);
    }

    /**
     * @return list<self>
     */
    public static function slotOccupyingCases(): array
    {
        return [self::Pending, self::Confirmed];
    }
}
