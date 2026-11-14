<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\CourierLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTrackingConsistencyTest extends TestCase
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

    private function createOrder(
        User $customer,
        User $courier
    ): Order {
        return Order::create([
            'order_number' => 'TRACK-' . uniqid(),
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

    private function createLocation(
        Order $order,
        User $courier,
        float $latitude,
        string $recordedAt
    ): CourierLocation {
        return CourierLocation::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'latitude' => $latitude,
            'longitude' => 101.4478,
            'accuracy' => 10,
            'heading' => 180,
            'speed' => 5,
            'recorded_at' => $recordedAt,
        ]);
    }

    public function test_customer_tracking_returns_latest_location(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createOrder($customer, $courier);

        $this->createLocation(
            $order,
            $courier,
            -0.5000,
            now()->subMinutes(5)->toISOString()
        );

        $this->createLocation(
            $order,
            $courier,
            -0.6000,
            now()->subMinute()->toISOString()
        );

        $response = $this->actingAs($customer)
            ->getJson("/api/orders/{$order->id}/tracking");

        $response->assertOk();

        $this->assertEquals(
            -0.6000,
            (float) $response->json('data.latitude')
        );
    }

    public function test_dispatcher_tracking_returns_latest_location(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');
        $dispatcher = $this->createUser('dispatcher');

        $order = $this->createOrder($customer, $courier);

        $this->createLocation(
            $order,
            $courier,
            -0.5000,
            now()->subMinutes(5)->toISOString()
        );

        $this->createLocation(
            $order,
            $courier,
            -0.6000,
            now()->subMinute()->toISOString()
        );

        $response = $this->actingAs($dispatcher)
            ->getJson("/api/dispatcher/orders/{$order->id}/tracking");

        $response->assertOk();

        $this->assertEquals(
            -0.6000,
            (float) $response->json('data.latitude')
        );
    }

    public function test_fleet_monitoring_returns_latest_location(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');
        $dispatcher = $this->createUser('dispatcher');

        $order = $this->createOrder($customer, $courier);

        $this->createLocation(
            $order,
            $courier,
            -0.5000,
            now()->subMinutes(5)->toISOString()
        );

        $this->createLocation(
            $order,
            $courier,
            -0.6000,
            now()->subMinute()->toISOString()
        );

        $response = $this->actingAs($dispatcher)
            ->getJson('/api/dispatcher/live-couriers');

        $response->assertOk();

        $this->assertEquals(
            -0.6000,
            (float) $response->json('data.0.latitude')
        );
    }

    public function test_tracking_uses_highest_id_when_timestamps_are_equal(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createOrder($customer, $courier);

        $timestamp = now()->subMinute()->toISOString();

        $this->createLocation(
            $order,
            $courier,
            -0.5000,
            $timestamp
        );

        $latest = $this->createLocation(
            $order,
            $courier,
            -0.6000,
            $timestamp
        );

        $response = $this->actingAs($customer)
            ->getJson("/api/orders/{$order->id}/tracking");

        $response->assertOk();

        $this->assertEquals(
            (float) $latest->latitude,
            (float) $response->json('data.latitude')
        );
    }
}
