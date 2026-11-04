<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_admins_can_visit_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_staff_are_sent_to_their_agenda(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('agenda'));
    }

    public function test_customers_are_sent_to_their_bookings(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('bookings.index'));
    }
}
