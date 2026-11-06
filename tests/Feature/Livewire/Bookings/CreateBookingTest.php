<?php

namespace Tests\Feature\Livewire\Bookings;

use App\Enums\BookingStatus;
use App\Enums\Weekday;
use App\Livewire\Bookings\CreateBooking;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 2027-03-15 is a Monday; 10:00 in Rome is 09:00 UTC on that day.
 */
class CreateBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.business_timezone' => 'Europe/Rome']);
        $this->travelTo(CarbonImmutable::parse('2027-03-01 12:00', 'UTC'));
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('bookings.create'))->assertRedirect(route('login'));
    }

    public function test_customers_can_open_the_booking_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('bookings.create'))
            ->assertOk();
    }

    public function test_staff_cannot_open_the_booking_page(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('bookings.create'))
            ->assertForbidden();
    }

    public function test_shows_the_free_times_on_the_business_clock(): void
    {
        [$operator, $service] = $this->operatorOffering();

        Livewire::actingAs(User::factory()->create())
            ->test(CreateBooking::class)
            ->set('serviceId', $service->id)
            ->set('operatorId', $operator->id)
            ->set('date', '2027-03-15')
            ->assertSee('09:00')
            ->assertSee('09:30')
            ->assertDontSee('10:00');
    }

    public function test_highlights_the_chosen_time(): void
    {
        [$operator, $service] = $this->operatorOffering();

        $html = Livewire::actingAs(User::factory()->create())
            ->test(CreateBooking::class)
            ->set('serviceId', $service->id)
            ->set('operatorId', $operator->id)
            ->set('date', '2027-03-15')
            ->set('slot', '2027-03-15 08:30:00')
            ->html();

        $this->assertSame(1, substr_count($html, 'aria-pressed="true"'));
        $this->assertMatchesRegularExpression('/wire:key="slot-2027-03-15 08:30:00"[^>]*aria-pressed="true"/', $html);
    }

    public function test_customer_books_a_free_time_and_is_sent_to_their_bookings(): void
    {
        [$operator, $service] = $this->operatorOffering();
        $customer = User::factory()->create();

        Livewire::actingAs($customer)
            ->test(CreateBooking::class)
            ->set('serviceId', $service->id)
            ->set('operatorId', $operator->id)
            ->set('date', '2027-03-15')
            ->set('slot', '2027-03-15 08:30:00')
            ->set('notes', 'Allergia al lattice')
            ->call('book')
            ->assertHasNoErrors()
            ->assertRedirect(route('bookings.index'));

        $this->assertDatabaseHas('bookings', [
            'customer_id' => $customer->id,
            'operator_id' => $operator->id,
            'starts_at' => '2027-03-15 08:30:00',
            'status' => BookingStatus::Confirmed->value,
            'notes' => 'Allergia al lattice',
        ]);
    }

    public function test_shows_an_error_when_the_time_was_taken_meanwhile(): void
    {
        [$operator, $service] = $this->operatorOffering();
        $customer = User::factory()->create();
        $component = Livewire::actingAs($customer)
            ->test(CreateBooking::class)
            ->set('serviceId', $service->id)
            ->set('operatorId', $operator->id)
            ->set('date', '2027-03-15')
            ->set('slot', '2027-03-15 08:30:00');
        Booking::factory()->for($operator)->confirmed()->create([
            'starts_at' => CarbonImmutable::parse('2027-03-15 08:30', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2027-03-15 09:00', 'UTC'),
        ]);

        $component->call('book')
            ->assertHasErrors('slot')
            ->assertSet('slot', '')
            ->assertNoRedirect();

        $this->assertDatabaseMissing('bookings', ['customer_id' => $customer->id]);
    }

    public function test_changing_service_clears_the_operator_when_several_offer_it(): void
    {
        [$firstOperator, $firstService] = $this->operatorOffering();
        [, $secondService] = $this->operatorOffering();
        $secondService->operators()->attach($firstOperator);

        Livewire::actingAs(User::factory()->create())
            ->test(CreateBooking::class)
            ->set('serviceId', $firstService->id)
            ->set('operatorId', $firstOperator->id)
            ->set('serviceId', $secondService->id)
            ->assertSet('operatorId', null);
    }

    public function test_changing_service_picks_the_only_operator_who_offers_it(): void
    {
        [$firstOperator, $firstService] = $this->operatorOffering();
        [$secondOperator, $secondService] = $this->operatorOffering();

        Livewire::actingAs(User::factory()->create())
            ->test(CreateBooking::class)
            ->set('serviceId', $firstService->id)
            ->set('operatorId', $firstOperator->id)
            ->set('serviceId', $secondService->id)
            ->assertSet('operatorId', $secondOperator->id)
            ->set('date', '2027-03-15')
            ->assertSee('09:30');
    }

    public function test_tells_the_customer_when_they_hold_too_many_upcoming_bookings(): void
    {
        [$operator, $service] = $this->operatorOffering();
        $customer = User::factory()->create();
        Booking::factory()->count(Booking::MAX_UPCOMING_PER_CUSTOMER)->for($customer, 'customer')->confirmed()->create();

        Livewire::actingAs($customer)
            ->test(CreateBooking::class)
            ->set('serviceId', $service->id)
            ->set('operatorId', $operator->id)
            ->set('date', '2027-03-15')
            ->set('slot', '2027-03-15 08:30:00')
            ->call('book')
            ->assertHasErrors('slot')
            ->assertNoRedirect();

        $this->assertDatabaseMissing('bookings', ['operator_id' => $operator->id]);
    }

    public function test_first_available_barber_shows_the_times_of_every_operator_and_books_one(): void
    {
        [$operator, $service] = $this->operatorOffering();
        $other = Operator::factory()->hasAttached($service)->create();
        $other->workingHours()->create(['weekday' => Weekday::Monday, 'start_time' => '11:00:00', 'end_time' => '11:30:00']);
        $customer = User::factory()->create();

        Livewire::actingAs($customer)
            ->test(CreateBooking::class)
            ->set('serviceId', $service->id)
            ->assertSet('operatorId', null)
            ->assertSee('Primo barbiere disponibile')
            ->set('operatorId', CreateBooking::ANY_OPERATOR)
            ->set('date', '2027-03-15')
            ->assertSee('09:30')
            ->assertSee('11:00')
            ->set('slot', '2027-03-15 10:00:00')
            ->call('book')
            ->assertHasNoErrors()
            ->assertRedirect(route('bookings.index'));

        $this->assertDatabaseHas('bookings', ['customer_id' => $customer->id, 'operator_id' => $other->id]);
    }

    public function test_customer_blocked_for_no_shows_sees_why_instead_of_the_form(): void
    {
        $customer = User::factory()->create();
        Booking::factory()->count(Booking::NO_SHOW_LIMIT)->for($customer, 'customer')->noShow()->create([
            'starts_at' => CarbonImmutable::parse('2027-02-01 09:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2027-02-01 10:00', 'UTC'),
        ]);

        Livewire::actingAs($customer)
            ->test(CreateBooking::class)
            ->assertSee('Prenotazione online sospesa')
            ->assertDontSee('Conferma prenotazione');
    }

    public function test_rejects_an_operator_who_does_not_offer_the_service(): void
    {
        [, $service] = $this->operatorOffering();
        $otherOperator = Operator::factory()->create();
        $customer = User::factory()->create();

        Livewire::actingAs($customer)
            ->test(CreateBooking::class)
            ->set('serviceId', $service->id)
            ->set('operatorId', $otherOperator->id)
            ->set('date', '2027-03-15')
            ->set('slot', '2027-03-15 08:30:00')
            ->call('book')
            ->assertHasErrors('operatorId');

        $this->assertDatabaseEmpty('bookings');
    }

    public function test_requires_a_well_formed_time(): void
    {
        [$operator, $service] = $this->operatorOffering();

        Livewire::actingAs(User::factory()->create())
            ->test(CreateBooking::class)
            ->set('serviceId', $service->id)
            ->set('operatorId', $operator->id)
            ->set('date', '2027-03-15')
            ->set('slot', 'tomorrow')
            ->call('book')
            ->assertHasErrors(['slot' => 'date_format']);

        $this->assertDatabaseEmpty('bookings');
    }

    public function test_admin_books_on_behalf_of_a_customer(): void
    {
        [$operator, $service] = $this->operatorOffering();
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateBooking::class)
            ->set('customerId', $customer->id)
            ->set('serviceId', $service->id)
            ->set('operatorId', $operator->id)
            ->set('date', '2027-03-15')
            ->set('slot', '2027-03-15 08:30:00')
            ->call('book')
            ->assertHasNoErrors()
            ->assertRedirect(route('agenda', ['operator' => $operator->id, 'date' => '2027-03-15']));

        $booking = Booking::query()->sole();
        $this->assertSame($customer->id, $booking->customer_id);
        $this->assertDatabaseHas('booking_status_histories', ['booking_id' => $booking->id, 'changed_by' => $admin->id]);
    }

    public function test_admin_must_choose_a_customer(): void
    {
        [$operator, $service] = $this->operatorOffering();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(CreateBooking::class)
            ->set('serviceId', $service->id)
            ->set('operatorId', $operator->id)
            ->set('date', '2027-03-15')
            ->set('slot', '2027-03-15 08:30:00')
            ->call('book')
            ->assertHasErrors(['customerId' => 'required']);

        $this->assertDatabaseEmpty('bookings');
    }

    public function test_customer_cannot_book_on_behalf_of_someone_else(): void
    {
        [$operator, $service] = $this->operatorOffering();
        $someoneElse = User::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(CreateBooking::class)
            ->set('customerId', $someoneElse->id)
            ->set('serviceId', $service->id)
            ->set('operatorId', $operator->id)
            ->set('date', '2027-03-15')
            ->set('slot', '2027-03-15 08:30:00')
            ->call('book')
            ->assertHasErrors(['customerId' => 'prohibited']);

        $this->assertDatabaseEmpty('bookings');
    }

    /**
     * A 30-minute service with an operator who works on Mondays from 09:00 to 10:00, Rome time.
     *
     * @return array{Operator, Service}
     */
    private function operatorOffering(): array
    {
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $operator = Operator::factory()->hasAttached($service)->create();
        $operator->workingHours()->create(['weekday' => Weekday::Monday, 'start_time' => '09:00:00', 'end_time' => '10:00:00']);

        return [$operator, $service];
    }
}
