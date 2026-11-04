<?php

namespace Tests\Unit\Enums;

use App\Enums\BookingStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BookingStatusTest extends TestCase
{
    /**
     * @return array<string, array{BookingStatus, BookingStatus, bool}>
     */
    public static function transitions(): array
    {
        $allowed = [
            'pending to confirmed' => [BookingStatus::Pending, BookingStatus::Confirmed],
            'pending to cancelled' => [BookingStatus::Pending, BookingStatus::Cancelled],
            'confirmed to completed' => [BookingStatus::Confirmed, BookingStatus::Completed],
            'confirmed to cancelled' => [BookingStatus::Confirmed, BookingStatus::Cancelled],
            'confirmed to no show' => [BookingStatus::Confirmed, BookingStatus::NoShow],
        ];

        $cases = [];

        foreach (BookingStatus::cases() as $from) {
            foreach (BookingStatus::cases() as $to) {
                $key = str_replace('_', ' ', "{$from->value} to {$to->value}");
                $cases[$key] = [$from, $to, array_key_exists($key, $allowed)];
            }
        }

        return $cases;
    }

    #[DataProvider('transitions')]
    public function test_allows_only_the_defined_transitions(BookingStatus $from, BookingStatus $to, bool $expected): void
    {
        $this->assertSame($expected, $from->canTransitionTo($to));
    }

    /**
     * @return array<string, array{BookingStatus, bool}>
     */
    public static function slotOccupation(): array
    {
        return [
            'pending' => [BookingStatus::Pending, true],
            'confirmed' => [BookingStatus::Confirmed, true],
            'completed' => [BookingStatus::Completed, false],
            'cancelled' => [BookingStatus::Cancelled, false],
            'no show' => [BookingStatus::NoShow, false],
        ];
    }

    #[DataProvider('slotOccupation')]
    public function test_only_open_bookings_occupy_their_slot(BookingStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->occupiesSlot());
    }
}
