<?php

namespace Tests\Feature\Livewire\Admin;

use App\Enums\Weekday;
use App\Livewire\Admin\EditOperator;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditOperatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_edit_even_their_own_operator_profile(): void
    {
        $operator = Operator::factory()->withStaffAccount()->create();

        $this->actingAs($operator->user)
            ->get(route('admin.operators.edit', $operator))
            ->assertForbidden();
    }

    public function test_admin_updates_the_name_and_the_services_offered(): void
    {
        $operator = Operator::factory()->hasAttached(Service::factory())->create();
        $newService = Service::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditOperator::class, ['operator' => $operator])
            ->set('name', 'Giulia Neri')
            ->set('serviceIds', [$newService->id])
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->assertSame('Giulia Neri', $operator->fresh()->name);
        $this->assertSame([$newService->id], $operator->services()->pluck('services.id')->all());
    }

    public function test_admin_adds_a_working_interval(): void
    {
        $operator = Operator::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditOperator::class, ['operator' => $operator])
            ->set('hourWeekday', '2')
            ->set('hourStart', '14:00')
            ->set('hourEnd', '18:00')
            ->call('addWorkingHour')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('working_hours', [
            'operator_id' => $operator->id,
            'weekday' => Weekday::Tuesday->value,
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
        ]);
    }

    public function test_rejects_a_working_interval_that_overlaps_another_on_the_same_day(): void
    {
        $operator = Operator::factory()->create();
        $operator->workingHours()->create(['weekday' => Weekday::Monday, 'start_time' => '09:00:00', 'end_time' => '13:00:00']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditOperator::class, ['operator' => $operator])
            ->set('hourWeekday', '1')
            ->set('hourStart', '12:00')
            ->set('hourEnd', '15:00')
            ->call('addWorkingHour')
            ->assertHasErrors('hourStart');

        $this->assertSame(1, $operator->workingHours()->count());
    }

    public function test_rejects_a_working_interval_that_ends_before_it_starts(): void
    {
        $operator = Operator::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditOperator::class, ['operator' => $operator])
            ->set('hourStart', '18:00')
            ->set('hourEnd', '09:00')
            ->call('addWorkingHour')
            ->assertHasErrors(['hourEnd' => 'after']);

        $this->assertDatabaseEmpty('working_hours');
    }

    public function test_time_off_typed_in_local_time_is_stored_in_utc(): void
    {
        config(['app.business_timezone' => 'Europe/Rome']);
        $this->travelTo(CarbonImmutable::parse('2027-07-01 12:00', 'UTC'));
        $operator = Operator::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditOperator::class, ['operator' => $operator])
            ->set('offStartsAt', '2027-08-09T00:00')
            ->set('offEndsAt', '2027-08-16T00:00')
            ->set('offReason', 'Ferie estive')
            ->call('addTimeOff')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('time_offs', [
            'operator_id' => $operator->id,
            'starts_at' => '2027-08-08 22:00:00',
            'ends_at' => '2027-08-15 22:00:00',
            'reason' => 'Ferie estive',
        ]);
    }

    public function test_warns_about_bookings_that_fall_in_new_time_off(): void
    {
        config(['app.business_timezone' => 'Europe/Rome']);
        $this->travelTo(CarbonImmutable::parse('2027-07-01 12:00', 'UTC'));
        $operator = Operator::factory()->create();
        $booking = Booking::factory()->for($operator)->confirmed()->create([
            'starts_at' => CarbonImmutable::parse('2027-08-10 08:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2027-08-10 09:00', 'UTC'),
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(EditOperator::class, ['operator' => $operator])
            ->set('offStartsAt', '2027-08-09T00:00')
            ->set('offEndsAt', '2027-08-16T00:00')
            ->call('addTimeOff')
            ->assertDispatched('toast-show', fn (string $event, array $params): bool => $params['dataset']['variant'] === 'warning'
                && str_contains($params['slots']['text'], 'Una prenotazione cade in questo periodo'));

        $this->assertModelExists($booking);
    }
}
