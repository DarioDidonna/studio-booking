<?php

namespace App\Models;

use App\Concerns\HasFormattedPrice;
use App\Enums\BookingStatus;
use App\Enums\DepositStatus;
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
 * @property int|null $customer_id Null once the customer has deleted their account.
 * @property int $operator_id
 * @property int $service_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property BookingStatus $status
 * @property int $price_cents
 * @property int|null $deposit_cents
 * @property DepositStatus|null $deposit_status
 * @property string|null $stripe_checkout_session_id
 * @property string|null $stripe_checkout_url
 * @property string|null $stripe_payment_intent_id
 * @property CarbonImmutable|null $payment_due_at
 * @property string|null $notes
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $reminder_due_at
 * @property CarbonImmutable|null $reminder_sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['customer_id', 'operator_id', 'service_id', 'starts_at', 'ends_at', 'status', 'price_cents', 'deposit_cents', 'deposit_status', 'stripe_checkout_session_id', 'stripe_checkout_url', 'stripe_payment_intent_id', 'payment_due_at', 'notes', 'cancelled_at', 'reminder_due_at', 'reminder_sent_at'])]
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
     * How many upcoming bookings a customer may hold at once, so nobody can fill the agenda.
     */
    public const MAX_UPCOMING_PER_CUSTOMER = 3;

    /**
     * How many days ahead the agenda is open for booking.
     */
    public const BOOKING_HORIZON_DAYS = 60;

    /**
     * After this many no-shows a customer can no longer book online.
     */
    public const NO_SHOW_LIMIT = 3;

    /**
     * The deposit a customer pays when booking online, taken off the bill on the day.
     */
    public const DEPOSIT_CENTS = 500;

    /**
     * How long an unpaid online booking holds its slot. Stripe Checkout
     * sessions cannot expire sooner than 30 minutes.
     */
    public const PAYMENT_WINDOW_MINUTES = 30;

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
            'deposit_cents' => 'integer',
            'deposit_status' => DepositStatus::class,
            'payment_due_at' => 'datetime',
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
