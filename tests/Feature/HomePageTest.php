<?php

namespace Tests\Feature;

use App\Enums\Weekday;
use App\Models\Operator;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_active_services_with_duration_and_price(): void
    {
        Service::factory()->create(['name' => 'Taglio', 'duration_minutes' => 30, 'price_cents' => 2000]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Taglio')
            ->assertSee('30 min')
            ->assertSee('20,00');
    }

    public function test_pages_cannot_be_framed_by_other_sites(): void
    {
        $this->get(route('home'))
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_hides_inactive_services(): void
    {
        Service::factory()->inactive()->create(['name' => 'Servizio sospeso']);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Servizio sospeso');
    }

    public function test_shows_active_barbers_with_the_services_they_offer(): void
    {
        $shave = Service::factory()->create(['name' => 'Rasatura tradizionale']);
        $retired = Service::factory()->inactive()->create(['name' => 'Servizio sospeso']);
        Operator::factory()->hasAttached([$shave, $retired])->create(['name' => 'Marco Bianchi']);
        Operator::factory()->create(['name' => 'Ex Barbiere', 'is_active' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Marco Bianchi')
            ->assertSee('Rasatura tradizionale')
            ->assertDontSee('Servizio sospeso')
            ->assertDontSee('Ex Barbiere');
    }

    public function test_opening_hours_merge_the_hours_of_active_barbers(): void
    {
        $morning = Operator::factory()->create();
        $morning->workingHours()->create(['weekday' => Weekday::Monday, 'start_time' => '09:00:00', 'end_time' => '13:00:00']);
        $afternoon = Operator::factory()->create();
        $afternoon->workingHours()->create(['weekday' => Weekday::Monday, 'start_time' => '12:00:00', 'end_time' => '19:00:00']);
        $afternoon->workingHours()->create(['weekday' => Weekday::Tuesday, 'start_time' => '14:00:00', 'end_time' => '18:00:00']);
        $afternoon->workingHours()->create(['weekday' => Weekday::Tuesday, 'start_time' => '09:00:00', 'end_time' => '13:00:00']);
        $inactive = Operator::factory()->create(['is_active' => false]);
        $inactive->workingHours()->create(['weekday' => Weekday::Sunday, 'start_time' => '09:00:00', 'end_time' => '12:00:00']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(['Lunedì', '09:00–19:00', 'Martedì', '09:00–13:00, 14:00–18:00', 'Mercoledì', 'Chiuso', 'Domenica', 'Chiuso']);
    }
}
