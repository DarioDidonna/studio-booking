<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServicePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_services(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create();

        $this->assertTrue($admin->can('viewAny', Service::class));
        $this->assertTrue($admin->can('create', Service::class));
        $this->assertTrue($admin->can('update', $service));
        $this->assertTrue($admin->can('delete', $service));
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
    public function test_non_admins_cannot_manage_services(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $service = Service::factory()->create();

        $this->assertFalse($user->can('viewAny', Service::class));
        $this->assertFalse($user->can('create', Service::class));
        $this->assertFalse($user->can('update', $service));
        $this->assertFalse($user->can('delete', $service));
    }
}
