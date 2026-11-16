<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

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
            'type' => OrderNotification::class,
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
            ->patchJson("/api/notifications/{$notification->id}/read")
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
            ->patchJson("/api/notifications/{$notification->id}/read")
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

    public function test_order_cancellation_notifies_courier_admin_and_dispatcher(): void
    {
        Notification::fake();

        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');
        $admin = $this->createUser('admin');
        $dispatcher = $this->createUser('dispatcher');

        $payload = [
            'delivery_type' => 'instant',
            'sender_name' => 'Sender Test',
            'sender_phone' => '08123456789',
            'pickup_address' => 'Pickup Test',
            'pickup_latitude' => 0,
            'pickup_longitude' => 0,
            'recipient_name' => 'Recipient Test',
            'recipient_phone' => '08198765432',
            'delivery_address' => 'Delivery Test',
            'delivery_latitude' => 0,
            'delivery_longitude' => 0.01,
        ];

        $this->actingAs($customer)
            ->postJson('/api/orders', $payload)
            ->assertCreated();

        $order = Order::latest('id')->firstOrFail();

        $order->update([
            'courier_id' => $courier->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($customer)
            ->postJson("/api/orders/{$order->id}/cancel", [
                'cancellation_reason' => 'Testing cancellation notification',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $notificationCheck = function (OrderNotification $notification) use ($order) {
            return $notification->type === 'order_cancelled'
                && $notification->orderId === $order->id;
        };

        Notification::assertSentTo(
            $courier,
            OrderNotification::class,
            $notificationCheck
        );

        Notification::assertSentTo(
            $admin,
            OrderNotification::class,
            $notificationCheck
        );

        Notification::assertSentTo(
            $dispatcher,
            OrderNotification::class,
            $notificationCheck
        );
    }
}

