<?php

namespace Tests\Feature\Policies;

use App\Models\Booking;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_own_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->assertTrue($booking->customer->can('view', $booking));
    }

    public function test_customer_cannot_view_another_customers_booking(): void
    {
        $booking = Booking::factory()->create();
        $otherCustomer = User::factory()->create();

        $this->assertFalse($otherCustomer->can('view', $booking));
    }

    public function test_staff_can_view_bookings_on_own_agenda(): void
    {
        $operator = Operator::factory()->withStaffAccount()->create();
        $booking = Booking::factory()->for($operator)->create();

        $this->assertTrue($operator->user->can('view', $booking));
    }

    public function test_staff_cannot_view_bookings_of_another_operator(): void
    {
        $booking = Booking::factory()->create();
        $otherStaff = Operator::factory()->withStaffAccount()->create()->user;

        $this->assertFalse($otherStaff->can('view', $booking));
    }

    public function test_admin_can_view_any_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->assertTrue(User::factory()->admin()->create()->can('view', $booking));
    }

    public function test_customers_and_admins_can_create_bookings(): void
    {
        $this->assertTrue(User::factory()->create()->can('create', Booking::class));
        $this->assertTrue(User::factory()->admin()->create()->can('create', Booking::class));
    }

    public function test_staff_cannot_create_bookings(): void
    {
        $this->assertFalse(User::factory()->staff()->create()->can('create', Booking::class));
    }

    public function test_customer_can_cancel_own_open_booking(): void
    {
        $booking = Booking::factory()->confirmed()->create();

        $this->assertTrue($booking->customer->can('cancel', $booking));
    }

    public function test_customer_cannot_cancel_another_customers_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->assertFalse(User::factory()->create()->can('cancel', $booking));
    }

    public function test_staff_cannot_cancel_bookings_on_own_agenda(): void
    {
        $operator = Operator::factory()->withStaffAccount()->create();
        $booking = Booking::factory()->for($operator)->create();

        $this->assertFalse($operator->user->can('cancel', $booking));
    }

    public function test_admin_can_cancel_any_open_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->assertTrue(User::factory()->admin()->create()->can('cancel', $booking));
    }

    public function test_assigned_staff_and_admins_can_update_the_status(): void
    {
        $operator = Operator::factory()->withStaffAccount()->create();
        $booking = Booking::factory()->for($operator)->confirmed()->create();

        $this->assertTrue($operator->user->can('updateStatus', $booking));
        $this->assertTrue(User::factory()->admin()->create()->can('updateStatus', $booking));
    }

    public function test_customers_and_other_staff_cannot_update_the_status(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        $otherStaff = Operator::factory()->withStaffAccount()->create()->user;

        $this->assertFalse($booking->customer->can('updateStatus', $booking));
        $this->assertFalse($otherStaff->can('updateStatus', $booking));
    }

    public function test_nobody_can_cancel_a_completed_booking(): void
    {
        $booking = Booking::factory()->completed()->create();

        $this->assertFalse($booking->customer->can('cancel', $booking));
        $this->assertFalse(User::factory()->admin()->create()->can('cancel', $booking));
    }
}
