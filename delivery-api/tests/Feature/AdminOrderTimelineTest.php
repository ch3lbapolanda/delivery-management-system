<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminOrderTimelineTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role): User
    {
        $roleModel = Role::firstOrCreate(
            ['slug' => $role],
            ['name' => ucfirst($role)]
        );

        return User::factory()->create([
            'role_id' => $roleModel->id,
        ]);
    }

    private function createOrder(User $customer): Order
    {
        Notification::fake();

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

        return Order::latest('id')->firstOrFail();
    }

    public function test_admin_can_view_order_detail_with_activity_timeline(): void
    {
        $customer = $this->createUser('customer');
        $admin = $this->createUser('admin');

        $order = $this->createOrder($customer);

        $order->statusHistories()->create([
            'changed_by' => $customer->id,
            'old_status' => null,
            'new_status' => 'pending',
            'note' => 'Order created',
        ]);

        $this->actingAs($admin)
            ->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath(
                'data.status_histories.0.changed_by.name',
                $customer->name
            )
            ->assertJsonPath(
                'data.status_histories.0.new_status',
                'pending'
            );
    }

    public function test_customer_cannot_access_admin_order_detail(): void
    {
        $customer = $this->createUser('customer');

        $order = $this->createOrder($customer);

        $this->actingAs($customer)
            ->getJson("/api/admin/orders/{$order->id}")
            ->assertForbidden();
    }
}