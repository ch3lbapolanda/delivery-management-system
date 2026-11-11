<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'customer'],
            [
                'name' => 'Customer',
                'description' => 'Test customer',
            ]
        );

        $user = new User();

        $user->forceFill([
            'name' => 'Customer Test',
            'email' => uniqid() . '@test.com',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'availability_status' => 'available',
        ]);

        $user->save();

        return $user;
    }

    private function validOrderPayload(): array
    {
        return [
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
    }

    public function test_customer_can_create_order_successfully(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $response = $this->postJson(
            '/api/orders',
            $this->validOrderPayload()
        );

        $response->assertCreated()
            ->assertJsonPath(
                'message',
                'Order created successfully.'
            )
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('pricing.delivery_type', 'instant')
            ->assertJsonPath('pricing.total_price', 10900);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'delivery_type' => 'instant',
            'status' => 'pending',
            'price' => '10900.00',
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'changed_by' => $customer->id,
            'old_status' => null,
            'new_status' => 'pending',
        ]);
    }

    public function test_backend_ignores_price_submitted_by_customer(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $payload = $this->validOrderPayload();

        // Customer mencoba mengirim harga palsu.
        $payload['price'] = 100;

        $response = $this->postJson(
            '/api/orders',
            $payload
        );

        $response->assertCreated();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(
            10900.0,
            (float) $order->price
        );

        $this->assertNotSame(
            100.0,
            (float) $order->price
        );
    }

    public function test_order_saves_pricing_details_snapshot(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $this->postJson(
            '/api/orders',
            $this->validOrderPayload()
        )->assertCreated();

        $order = Order::latest('id')->firstOrFail();

        $this->assertIsArray($order->pricing_details);

        $this->assertSame(
            'instant',
            $order->pricing_details['delivery_type']
        );

        $this->assertSame(
            5000,
            $order->pricing_details['base_price']
        );

        $this->assertSame(
            2900,
            $order->pricing_details['distance_fee']
        );

        $this->assertSame(
            10900,
            $order->pricing_details['total_price']
        );
    }

    public function test_guest_cannot_create_order(): void
    {
        $response = $this->postJson(
            '/api/orders',
            $this->validOrderPayload()
        );

        $response->assertUnauthorized();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_rejects_invalid_coordinates_and_zero_distance(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $invalidPayload = $this->validOrderPayload();
        $invalidPayload['pickup_latitude'] = 91;

        $this->postJson(
            '/api/orders',
            $invalidPayload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickup_latitude']);

        $zeroDistancePayload = $this->validOrderPayload();
        $zeroDistancePayload['delivery_latitude'] = 0;
        $zeroDistancePayload['delivery_longitude'] = 0;

        $this->postJson(
            '/api/orders',
            $zeroDistancePayload
        )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Delivery distance must be greater than zero.',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }
}
