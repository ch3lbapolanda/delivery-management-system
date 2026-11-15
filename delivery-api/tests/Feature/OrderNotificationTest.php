<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class OrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role = 'customer'): User
    {
        $roleModel = Role::firstOrCreate(
            ['slug' => $role],
            ['name' => ucfirst($role)]
        );

        return User::factory()->create([
            'role_id' => $roleModel->id,
        ]);
    }

    private function createNotification(
    User $user,
    bool $read = false
) {
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\OrderNotification',
        'data' => [
            'type' => 'order_created',
            'title' => 'New Order',
            'message' => 'Your order has been created.',
            'order_id' => 1,
            'order_number' => 'KM-TEST-001',
            'data' => [],
        ],
        'read_at' => $read ? now() : null,
    ]);
    }

    public function test_authenticated_user_can_get_notifications(): void
    {
        $user = $this->createUser();

        $this->createNotification($user);
        $this->createNotification($user, true);

        $this->actingAs($user)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(2, 'data.data');
    }

    public function test_user_can_get_unread_notification_count(): void
    {
        $user = $this->createUser();

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, true);

        $this->actingAs($user)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = $this->createUser();

        $notification = $this->createNotification($user);

        $this->actingAs($user)
            ->patchJson(
                "/api/notifications/{$notification->id}/read"
            )
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'notifiable_id' => $user->id,
        ]);

        $this->assertNotNull(
            $notification->fresh()->read_at
        );
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $userOne = $this->createUser();
        $userTwo = $this->createUser();

        $notification = $this->createNotification($userOne);

        $this->actingAs($userTwo)
            ->patchJson(
                "/api/notifications/{$notification->id}/read"
            )
            ->assertNotFound();

        $this->assertNull(
            $notification->fresh()->read_at
        );
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = $this->createUser();

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, true);

        $this->actingAs($user)
            ->patchJson('/api/notifications/read-all')
            ->assertOk();

        $this->assertSame(
            0,
            $user->unreadNotifications()->count()
        );
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->getJson('/api/notifications')
            ->assertUnauthorized();

        $this->getJson('/api/notifications/unread-count')
            ->assertUnauthorized();
    }

    public function test_user_cannot_access_another_users_notifications(): void
    {
        $userOne = $this->createUser();
        $userTwo = $this->createUser();

        $this->createNotification($userOne);

        $this->actingAs($userTwo)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data.data')
            ->assertJsonPath('unread_count', 0);
    }
}

