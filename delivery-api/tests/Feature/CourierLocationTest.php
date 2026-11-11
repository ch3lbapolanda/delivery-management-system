<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierLocationTest extends TestCase
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

    private function createInTransitOrder(
        User $customer,
        User $courier
    ): Order {
        return Order::create([
            'order_number' => 'GPS-' . uniqid(),
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

    private function locationPayload(): array
    {
        return [
            'latitude' => -0.5071,
            'longitude' => 101.4478,
            'accuracy' => 10.5,
            'heading' => 180,
            'speed' => 5.2,
            'recorded_at' => now()->toISOString(),
        ];
    }

    public function test_courier_can_submit_location_for_own_in_transit_order(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createInTransitOrder(
            $customer,
            $courier
        );

        $response = $this->actingAs($courier)
            ->postJson(
                "/api/courier/orders/{$order->id}/location",
                $this->locationPayload()
            );

        $response->assertCreated()
            ->assertJsonPath(
                'message',
                'Courier location recorded successfully.'
            );

        $this->assertDatabaseHas('courier_locations', [
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'latitude' => -0.5071,
            'longitude' => 101.4478,
        ]);
    }

    public function test_guest_cannot_submit_courier_location(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createInTransitOrder(
            $customer,
            $courier
        );

        $response = $this->postJson(
            "/api/courier/orders/{$order->id}/location",
            $this->locationPayload()
        );

        $response->assertUnauthorized();
    }

    public function test_courier_cannot_submit_location_for_another_courier_order(): void
    {
        $customer = $this->createUser('customer');
        $courierOne = $this->createUser('courier');
        $courierTwo = $this->createUser('courier');

        $order = $this->createInTransitOrder(
            $customer,
            $courierOne
        );

        $response = $this->actingAs($courierTwo)
            ->postJson(
                "/api/courier/orders/{$order->id}/location",
                $this->locationPayload()
            );

        $response->assertNotFound();

        $this->assertDatabaseCount('courier_locations', 0);
    }

    public function test_courier_cannot_submit_location_for_non_transit_order(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createInTransitOrder(
            $customer,
            $courier
        );

        $order->update([
            'status' => 'picked_up',
        ]);

        $response = $this->actingAs($courier)
            ->postJson(
                "/api/courier/orders/{$order->id}/location",
                $this->locationPayload()
            );

        $response->assertNotFound();

        $this->assertDatabaseCount('courier_locations', 0);
    }

    public function test_location_endpoint_rejects_invalid_coordinates(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createInTransitOrder(
            $customer,
            $courier
        );

        $payload = $this->locationPayload();

        $payload['latitude'] = 120;
        $payload['longitude'] = 200;

        $response = $this->actingAs($courier)
            ->postJson(
                "/api/courier/orders/{$order->id}/location",
                $payload
            );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'latitude',
                'longitude',
            ]);

        $this->assertDatabaseCount('courier_locations', 0);
    }

    public function test_customer_cannot_submit_courier_location(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createInTransitOrder(
            $customer,
            $courier
        );

        $response = $this->actingAs($customer)
            ->postJson(
                "/api/courier/orders/{$order->id}/location",
                $this->locationPayload()
            );

        $response->assertForbidden();

        $this->assertDatabaseCount('courier_locations', 0);
    }
}

