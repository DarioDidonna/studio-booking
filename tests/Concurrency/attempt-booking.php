<?php

/**
 * Tries to book one slot from its own PHP process, so two of these can race.
 *
 * Availability is slowed down on purpose: both processes are then guaranteed
 * to check the slot before either one inserts its booking, and only the
 * operator row lock in BookingService can prevent a double booking.
 *
 * Usage: php attempt-booking.php {customerId} {operatorId} {serviceId} {startsAtUtc}
 * Prints "booked" or "taken".
 */

use App\Exceptions\SlotNotAvailableException;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Collection;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$app->bind(AvailabilityService::class, fn () => new class extends AvailabilityService
{
    public function availableSlots(Operator $operator, Service $service, string $date): Collection
    {
        $slots = parent::availableSlots($operator, $service, $date);

        usleep(700_000);

        return $slots;
    }
});

[, $customerId, $operatorId, $serviceId, $startsAt] = $argv;

$customer = User::query()->findOrFail($customerId);

try {
    $app->make(BookingService::class)->book(
        $customer,
        Operator::query()->findOrFail($operatorId),
        Service::query()->findOrFail($serviceId),
        CarbonImmutable::parse($startsAt, 'UTC'),
        $customer,
    );

    echo 'booked';
} catch (SlotNotAvailableException) {
    echo 'taken';
}
