<?php

namespace Tests\Feature\Livewire\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\Operators;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class OperatorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.operators'))
            ->assertForbidden();
    }

    public function test_admin_creates_an_operator_without_an_account(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Operators::class)
            ->set('name', 'Luca Verdi')
            ->call('create')
            ->assertHasNoErrors();

        $operator = Operator::query()->sole();
        $this->assertSame('Luca Verdi', $operator->name);
        $this->assertNull($operator->user_id);
    }

    public function test_operator_with_an_email_gets_a_staff_account_and_a_link_to_set_the_password(): void
    {
        Notification::fake();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Operators::class)
            ->set('name', 'Luca Verdi')
            ->set('email', 'luca@example.com')
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.operators.edit', Operator::query()->sole()));

        $staff = User::query()->where('email', 'luca@example.com')->sole();
        $this->assertSame(UserRole::Staff, $staff->role);
        $this->assertSame($staff->id, Operator::query()->sole()->user_id);
        Notification::assertSentTo($staff, ResetPassword::class);
    }

    public function test_rejects_an_email_that_already_has_an_account(): void
    {
        User::factory()->create(['email' => 'luca@example.com']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Operators::class)
            ->set('name', 'Luca Verdi')
            ->set('email', 'luca@example.com')
            ->call('create')
            ->assertHasErrors(['email' => 'unique']);

        $this->assertDatabaseEmpty('operators');
    }

    public function test_admin_deactivates_an_operator(): void
    {
        $operator = Operator::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Operators::class)
            ->call('toggleActive', $operator->id);

        $this->assertFalse($operator->fresh()->is_active);
    }
}
