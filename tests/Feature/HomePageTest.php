<?php

namespace Tests\Feature;

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

    public function test_hides_inactive_services(): void
    {
        Service::factory()->inactive()->create(['name' => 'Servizio sospeso']);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Servizio sospeso');
    }
}
