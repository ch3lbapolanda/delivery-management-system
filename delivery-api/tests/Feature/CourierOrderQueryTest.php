<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierOrderQueryTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $roleSlug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucfirst($roleSlug)]
        );

        $user = User::factory()->create();

        $user->forceFill([
            'role_id' => $role->id,
            'is_active' => true,
        ])->save();

        return $user;
    }

    private function createOrder(
        User $customer,
        User $courier
    ): Order {
        return Order::create([
            'order_number' => 'KM-' . strtoupper(uniqid()),
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
            'delivery_type' => 'instant',
            'status' => 'assigned',
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
        ]);
    }

    public function test_courier_can_only_see_own_orders(): void
    {
        $customer = $this->createUser('customer');
        $courierOne = $this->createUser('courier');
        $courierTwo = $this->createUser('courier');

        $ownOrder = $this->createOrder($customer, $courierOne);
        $otherOrder = $this->createOrder($customer, $courierTwo);

        $response = $this->actingAs($courierOne)
            ->getJson('/api/courier/orders');

        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment([
                'id' => $ownOrder->id,
            ])
            ->assertJsonMissing([
                'id' => $otherOrder->id,
            ]);
    }

    public function test_courier_can_view_own_order_detail(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createOrder($customer, $courier);

        $response = $this->actingAs($courier)
            ->getJson("/api/courier/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.courier_id', $courier->id);
    }

    public function test_courier_cannot_view_another_couriers_order_detail(): void
    {
        $customer = $this->createUser('customer');
        $ownerCourier = $this->createUser('courier');
        $otherCourier = $this->createUser('courier');

        $order = $this->createOrder($customer, $ownerCourier);

        $response = $this->actingAs($otherCourier)
            ->getJson("/api/courier/orders/{$order->id}");

        $response->assertNotFound();
    }

    public function test_guest_cannot_access_courier_order_endpoints(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createOrder($customer, $courier);

        $this->getJson('/api/courier/orders')
            ->assertUnauthorized();

        $this->getJson("/api/courier/orders/{$order->id}")
            ->assertUnauthorized();
    }
}
