<?php

namespace App\Models;

use App\Concerns\HasFormattedPrice;
use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $operator_id
 * @property int $service_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property BookingStatus $status
 * @property int $price_cents
 * @property string|null $notes
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $reminder_due_at
 * @property CarbonImmutable|null $reminder_sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['customer_id', 'operator_id', 'service_id', 'starts_at', 'ends_at', 'status', 'price_cents', 'notes', 'cancelled_at', 'reminder_due_at', 'reminder_sent_at'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, HasFormattedPrice;

    /**
     * How long before the start a customer may still cancel for free.
     */
    public const FREE_CANCELLATION_HOURS = 24;

    /**
     * How long before the start the customer is reminded.
     */
    public const REMINDER_HOURS = 24;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => BookingStatus::class,
            'price_cents' => 'integer',
            'cancelled_at' => 'datetime',
            'reminder_due_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * The start time on the business clock, for display.
     */
    public function localStartsAt(): CarbonImmutable
    {
        return $this->starts_at->setTimezone(config('app.business_timezone'));
    }

    public function freeCancellationEndsAt(): CarbonImmutable
    {
        return $this->starts_at->subHours(self::FREE_CANCELLATION_HOURS);
    }

    /**
     * Bookings starting in [from, to). Times are converted to UTC first,
     * because the database stores them without a timezone.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function whereBetweenStarts(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->where('starts_at', '>=', $from->utc())->where('starts_at', '<', $to->utc());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Operator, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<BookingStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class);
    }
}
