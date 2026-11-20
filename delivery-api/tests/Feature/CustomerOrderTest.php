<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOrderTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $roleSlug = 'customer'): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => ucfirst($roleSlug),
                'description' => 'Test role',
            ]
        );

        $user = new User();

        $user->forceFill([
            'name' => ucfirst($roleSlug) . ' Test',
            'email' => uniqid() . '@test.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
            'availability_status' => 'available',
            'is_active' => true,
        ]);

        $user->save();

        return $user;
    }

    private function createOrder(
        User $customer,
        ?User $courier = null,
        string $status = 'pending'
    ): Order {
        return Order::create([
            'order_number' => 'KM-' . strtoupper(uniqid()),
            'customer_id' => $customer->id,
            'courier_id' => $courier?->id,
            'delivery_type' => 'instant',
            'status' => $status,
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
            'price' => 10900,
        ]);
    }

    public function test_customer_order_list_only_contains_own_orders(): void
    {
        $customerA = $this->createUser();
        $customerB = $this->createUser();

        $orderA = $this->createOrder($customerA);
        $this->createOrder($customerB);

        Sanctum::actingAs($customerA);

        $response = $this->getJson('/api/orders');

        $response->assertOk()
            ->assertJsonPath('message', 'Orders retrieved successfully.')
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $orderA->id);
    }

    public function test_customer_can_view_own_order_detail(): void
    {
        $customer = $this->createUser();
        $order = $this->createOrder($customer);

        Sanctum::actingAs($customer);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.customer_id', $customer->id);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $customerA = $this->createUser();
        $customerB = $this->createUser();

        $order = $this->createOrder($customerB);

        Sanctum::actingAs($customerA);

        $this->getJson("/api/orders/{$order->id}")
            ->assertNotFound();
    }

    public function test_customer_cannot_cancel_another_customers_order(): void
    {
        $customerA = $this->createUser();
        $customerB = $this->createUser();

        $order = $this->createOrder($customerB);

        Sanctum::actingAs($customerA);

        $this->postJson("/api/orders/{$order->id}/cancel", [
            'cancellation_reason' => 'Test cancellation',
        ])->assertNotFound();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => $customerB->id,
            'status' => 'pending',
        ]);
    }

    public function test_customer_cannot_track_another_customers_order(): void
    {
        $customerA = $this->createUser();
        $customerB = $this->createUser();
        $courier = $this->createUser('courier');

        $order = $this->createOrder(
            $customerB,
            $courier,
            'in_transit'
        );

        Sanctum::actingAs($customerA);

        $this->getJson("/api/orders/{$order->id}/tracking")
            ->assertNotFound();
    }

    public function test_customer_can_cancel_own_pending_order(): void
    {
        Notification::fake();

        $customer = $this->createUser();
        $order = $this->createOrder($customer);

        Sanctum::actingAs($customer);

        $response = $this->postJson(
            "/api/orders/{$order->id}/cancel",
            [
                'cancellation_reason' => 'Changed my delivery plan.',
            ]
        );

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Order cancelled successfully.'
            )
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath(
                'data.cancellation_reason',
                'Changed my delivery plan.'
            );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Changed my delivery plan.',
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'changed_by' => $customer->id,
            'old_status' => 'pending',
            'new_status' => 'cancelled',
            'note' => 'Changed my delivery plan.',
        ]);
    }

    public function test_customer_cannot_cancel_order_already_in_transit(): void
    {
        $customer = $this->createUser();
        $courier = $this->createUser('courier');

        $order = $this->createOrder(
            $customer,
            $courier,
            'in_transit'
        );

        Sanctum::actingAs($customer);

        $this->postJson(
            "/api/orders/{$order->id}/cancel",
            [
                'cancellation_reason' => 'Changed my mind.',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'This order cannot be cancelled.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'in_transit',
            'cancellation_reason' => null,
        ]);
    }

    public function test_customer_cancellation_requires_reason(): void
    {
        $customer = $this->createUser();
        $order = $this->createOrder($customer);

        Sanctum::actingAs($customer);

        $this->postJson(
            "/api/orders/{$order->id}/cancel",
            []
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cancellation_reason']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }
}
