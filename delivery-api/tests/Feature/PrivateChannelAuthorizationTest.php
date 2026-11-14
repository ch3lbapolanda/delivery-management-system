<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateChannelAuthorizationTest extends TestCase
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

    private function createOrder(User $customer, User $courier): Order
    {
        return Order::create([
            'order_number' => 'AUTH-' . uniqid(),
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
            'delivery_type' => 'instant',
            'status' => 'in_transit',
            'sender_name' => 'Sender',
            'sender_phone' => '08123456789',
            'pickup_address' => 'Pickup Address',
            'pickup_latitude' => -0.5071,
            'pickup_longitude' => 101.4478,
            'recipient_name' => 'Recipient',
            'recipient_phone' => '08123456789',
            'delivery_address' => 'Delivery Address',
            'delivery_latitude' => -0.5100,
            'delivery_longitude' => 101.4500,
            'price' => 10000,
        ]);
    }

    private function authorizeChannel(User $user, int $orderId)
    {
        return $this->actingAs($user)->postJson(
            '/api/broadcasting/auth',
            [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-orders.' . $orderId,
            ]
        );
    }

    public function test_customer_can_access_own_order_channel(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');
        $order = $this->createOrder($customer, $courier);

        $this->authorizeChannel($customer, $order->id)
            ->assertOk();
    }

    public function test_customer_cannot_access_another_customer_order_channel(): void
    {
        $customerOne = $this->createUser('customer');
        $customerTwo = $this->createUser('customer');
        $courier = $this->createUser('courier');
        $order = $this->createOrder($customerOne, $courier);

        $this->authorizeChannel($customerTwo, $order->id)
            ->assertForbidden();
    }

    public function test_assigned_courier_can_access_order_channel(): void
    {
        $customer = $this->createUser('customer');
        $assignedCourier = $this->createUser('courier');
        $order = $this->createOrder($customer, $assignedCourier);

        $this->authorizeChannel($assignedCourier, $order->id)
            ->assertOk();
    }

    public function test_unassigned_courier_cannot_access_order_channel(): void
    {
        $customer = $this->createUser('customer');
        $assignedCourier = $this->createUser('courier');
        $otherCourier = $this->createUser('courier');
        $order = $this->createOrder($customer, $assignedCourier);

        $this->authorizeChannel($otherCourier, $order->id)
            ->assertForbidden();
    }

    public function test_guest_cannot_access_order_channel(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');
        $order = $this->createOrder($customer, $courier);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-orders.' . $order->id,
        ])->assertUnauthorized();
    }

    public function test_admin_can_access_order_channel(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');
        $admin = $this->createUser('admin');
        $order = $this->createOrder($customer, $courier);

        $this->authorizeChannel($admin, $order->id)
            ->assertOk();
    }

    public function test_dispatcher_can_access_order_channel(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');
        $dispatcher = $this->createUser('dispatcher');
        $order = $this->createOrder($customer, $courier);

        $this->authorizeChannel($dispatcher, $order->id)
            ->assertOk();
    }

    public function test_user_cannot_access_nonexistent_order_channel(): void
    {
        $customer = $this->createUser('customer');

        $this->authorizeChannel($customer, 999999)
            ->assertForbidden();
    }
}

