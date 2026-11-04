<?php

namespace Tests\Feature\Concurrency;

use App\Enums\Weekday;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Runs two real PHP processes against a real database. Data is committed
 * (truncation instead of transactions) so that both processes can see it.
 */
#[Group('concurrency')]
class DoubleBookingTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite ignores row locks: run this test on MySQL or PostgreSQL.');
        }

        // Truncation only runs before each test: clean up the committed rows too,
        // or the transactional tests that follow would find them.
        $this->beforeApplicationDestroyed(fn () => $this->truncateTablesForAllConnections());
    }

    public function test_two_simultaneous_requests_for_the_same_slot_produce_one_booking(): void
    {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $operator = Operator::factory()->hasAttached($service)->create();
        foreach (Weekday::cases() as $weekday) {
            $operator->workingHours()->create(['weekday' => $weekday, 'start_time' => '08:00:00', 'end_time' => '20:00:00']);
        }
        $first = User::factory()->create();
        $second = User::factory()->create();
        $startsAt = CarbonImmutable::now(config('app.business_timezone'))->addDay()->setTime(10, 0)->utc()->toDateTimeString();

        $results = Process::concurrently(fn (Pool $pool) => [
            $pool->as('first')->command($this->attempt($first, $operator, $service, $startsAt)),
            $pool->as('second')->command($this->attempt($second, $operator, $service, $startsAt)),
        ]);

        $outcomes = collect($results)->map(fn (ProcessResult $result): string => trim($result->output()).trim($result->errorOutput()))->sort()->values()->all();
        $this->assertSame(['booked', 'taken'], $outcomes);
        $this->assertSame(1, Booking::query()->count());
    }

    /**
     * @return list<string>
     */
    private function attempt(User $customer, Operator $operator, Service $service, string $startsAt): array
    {
        return [
            PHP_BINARY,
            base_path('tests/Concurrency/attempt-booking.php'),
            (string) $customer->id,
            (string) $operator->id,
            (string) $service->id,
            $startsAt,
        ];
    }
}
