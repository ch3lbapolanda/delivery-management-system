<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Events\CourierLocationUpdated;
use Illuminate\Support\Facades\Event;

class CourierLocationTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
{
    parent::setUp();

    Event::fake([
        CourierLocationUpdated::class,
    ]);
}

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
  public function test_courier_cannot_spoof_courier_id_in_location_payload(): void
  {
    $customer = $this->createUser('customer');
    $courierOne = $this->createUser('courier');
    $courierTwo = $this->createUser('courier');

    $order = $this->createInTransitOrder(
        $customer,
        $courierOne
    );

    $payload = $this->locationPayload();
    $payload['courier_id'] = $courierTwo->id;

    $response = $this->actingAs($courierOne)
        ->postJson(
            "/api/courier/orders/{$order->id}/location",
            $payload
        );

    $response->assertCreated();

    $this->assertDatabaseHas('courier_locations', [
        'order_id' => $order->id,
        'courier_id' => $courierOne->id,
    ]);

    $this->assertDatabaseMissing('courier_locations', [
        'order_id' => $order->id,
        'courier_id' => $courierTwo->id,
    ]);
  }

  
    public function test_latest_location_is_broadcast(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createInTransitOrder($customer, $courier);

        $payload = $this->locationPayload();
        $payload['recorded_at'] = now()->subMinute()->toISOString();

        $this->actingAs($courier)->postJson(
            "/api/courier/orders/{$order->id}/location",
            $payload
        )->assertCreated();

        Event::assertDispatched(CourierLocationUpdated::class);
    }

    public function test_older_location_is_saved_but_not_broadcast(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createInTransitOrder($customer, $courier);

        $newerPayload = $this->locationPayload();
        $newerPayload['recorded_at'] = now()->subMinute()->toISOString();

        $this->actingAs($courier)->postJson(
            "/api/courier/orders/{$order->id}/location",
            $newerPayload
        )->assertCreated();

        Event::fake([CourierLocationUpdated::class]);

        $olderPayload = $this->locationPayload();
        $olderPayload['recorded_at'] = now()->subMinutes(2)->toISOString();

        $this->actingAs($courier)->postJson(
            "/api/courier/orders/{$order->id}/location",
            $olderPayload
        )->assertCreated();

        $this->assertDatabaseCount('courier_locations', 2);
        Event::assertNotDispatched(CourierLocationUpdated::class);
    }

    public function test_location_with_same_timestamp_is_not_broadcast(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createInTransitOrder($customer, $courier);

        $timestamp = now()->subMinute()->toISOString();

        $payload = $this->locationPayload();
        $payload['recorded_at'] = $timestamp;

        $this->actingAs($courier)->postJson(
            "/api/courier/orders/{$order->id}/location",
            $payload
        )->assertCreated();

        Event::fake([CourierLocationUpdated::class]);

        $this->actingAs($courier)->postJson(
            "/api/courier/orders/{$order->id}/location",
            $payload
        )->assertCreated();

        Event::assertNotDispatched(CourierLocationUpdated::class);
    }
public function test_location_endpoint_rejects_future_timestamp(): void
{
    $customer = $this->createUser('customer');
    $courier = $this->createUser('courier');

    $order = $this->createInTransitOrder(
        $customer,
        $courier
    );

    $payload = $this->locationPayload();

    $payload['recorded_at'] = now()
        ->addMinutes(3)
        ->toISOString();

    $response = $this->actingAs($courier)
        ->postJson(
            "/api/courier/orders/{$order->id}/location",
            $payload
        );

    $response->assertUnprocessable()
        ->assertJson([
            'message' => 'GPS timestamp cannot be more than 2 minutes in the future.',
        ]);

    $this->assertDatabaseCount('courier_locations', 0);

    Event::assertNotDispatched(CourierLocationUpdated::class);
}


}

