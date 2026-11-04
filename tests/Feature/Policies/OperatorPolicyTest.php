<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperatorPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_operators(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = Operator::factory()->create();

        $this->assertTrue($admin->can('viewAny', Operator::class));
        $this->assertTrue($admin->can('create', Operator::class));
        $this->assertTrue($admin->can('update', $operator));
        $this->assertTrue($admin->can('delete', $operator));
    }

    /**
     * @return array<string, array{UserRole}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'customer' => [UserRole::Customer],
            'staff' => [UserRole::Staff],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_manage_operators(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $operator = Operator::factory()->create();

        $this->assertFalse($user->can('viewAny', Operator::class));
        $this->assertFalse($user->can('create', Operator::class));
        $this->assertFalse($user->can('update', $operator));
        $this->assertFalse($user->can('delete', $operator));
    }

    public function test_staff_can_view_only_their_own_agenda(): void
    {
        $operator = Operator::factory()->withStaffAccount()->create();
        $otherOperator = Operator::factory()->withStaffAccount()->create();

        $this->assertTrue($operator->user->can('viewAgenda', $operator));
        $this->assertFalse($operator->user->can('viewAgenda', $otherOperator));
    }

    public function test_admin_can_view_every_agenda(): void
    {
        $operator = Operator::factory()->create();

        $this->assertTrue(User::factory()->admin()->create()->can('viewAgenda', $operator));
    }

    public function test_customers_cannot_view_an_agenda(): void
    {
        $operator = Operator::factory()->create();

        $this->assertFalse(User::factory()->create()->can('viewAgenda', $operator));
    }
}
