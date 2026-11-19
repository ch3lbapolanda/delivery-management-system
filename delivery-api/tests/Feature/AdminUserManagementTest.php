<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $role = Role::where('slug', 'admin')->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_dispatcher_account(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Dispatcher Test',
            'email' => 'dispatcher@test.com',
            'password' => 'password123',
            'role' => 'dispatcher',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Dispatcher Test')
            ->assertJsonPath('data.role.slug', 'dispatcher')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('users', [
            'email' => 'dispatcher@test.com',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_courier_account(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Courier Test',
            'email' => 'courier@test.com',
            'password' => 'password123',
            'role' => 'courier',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Courier Test')
            ->assertJsonPath('data.role.slug', 'courier')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_admin_cannot_create_another_admin_account(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Another Admin',
            'email' => 'another-admin@test.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('users', [
            'email' => 'another-admin@test.com',
        ]);
    }

    public function test_admin_can_update_dispatcher_account(): void
    {
        $admin = $this->createAdmin();

        $dispatcherRole = Role::where('slug', 'dispatcher')->firstOrFail();

        $dispatcher = User::factory()->create([
            'name' => 'Old Dispatcher',
            'email' => 'old-dispatcher@test.com',
            'role_id' => $dispatcherRole->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson(
            "/api/admin/users/{$dispatcher->id}",
            [
                'name' => 'Updated User',
                'email' => 'updated@test.com',
                'role' => 'courier',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated User')
            ->assertJsonPath('data.email', 'updated@test.com')
            ->assertJsonPath('data.role.slug', 'courier');

        $this->assertDatabaseHas('users', [
            'id' => $dispatcher->id,
            'name' => 'Updated User',
            'email' => 'updated@test.com',
        ]);
    }

    public function test_admin_cannot_update_user_with_duplicate_email(): void
    {
        $admin = $this->createAdmin();

        $dispatcherRole = Role::where('slug', 'dispatcher')->firstOrFail();

        $user = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'is_active' => true,
        ]);

        User::factory()->create([
            'email' => 'existing@test.com',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson(
            "/api/admin/users/{$user->id}",
            [
                'name' => 'Updated User',
                'email' => 'existing@test.com',
                'role' => 'courier',
            ]
        );

        $response->assertUnprocessable();
    }

    public function test_admin_account_cannot_be_updated_through_user_endpoint(): void
    {
        $admin = $this->createAdmin();

        Sanctum::actingAs($admin);

        $response = $this->putJson(
            "/api/admin/users/{$admin->id}",
            [
                'name' => 'Changed Admin',
                'email' => 'changed-admin@test.com',
                'role' => 'courier',
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
        ]);
    }
}

