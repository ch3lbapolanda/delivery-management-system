<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Role;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DispatcherWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $roleSlug): User
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
            'is_active' => true,
            'availability_status' => 'available',
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

    public function test_dispatcher_can_view_only_available_couriers(): void
    {
        $dispatcher = $this->createUser('dispatcher');
        $customer = $this->createUser('customer');

        $available = $this->createUser('courier');

        $offline = $this->createUser('courier');
        $offline->forceFill(['availability_status' => 'offline',])->save();

        $busy = $this->createUser('courier');
        $this->createOrder($customer, $busy, 'assigned');

        Sanctum::actingAs($dispatcher);

        $response = $this->getJson('/api/dispatcher/couriers');
      

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $available->id);
    }
  

    public function test_dispatcher_can_assign_pending_order_to_available_courier(): void
    {
        Notification::fake();

        $dispatcher = $this->createUser('dispatcher');
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createOrder($customer);

        Sanctum::actingAs($dispatcher);

        $response = $this->postJson(
            "/api/dispatcher/orders/{$order->id}/assign",
            ['courier_id' => $courier->id]
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Courier assigned successfully.')
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.courier_id', $courier->id);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'courier_id' => $courier->id,
            'status' => 'assigned',
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'changed_by' => $dispatcher->id,
            'old_status' => 'pending',
            'new_status' => 'assigned',
        ]);

        Notification::assertSentTo(
            $courier,
            OrderNotification::class
        );
    }

    public function test_dispatcher_cannot_assign_non_pending_order(): void
    {
        $dispatcher = $this->createUser('dispatcher');
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createOrder(
            $customer,
            null,
            'in_transit'
        );

        Sanctum::actingAs($dispatcher);

        $this->postJson(
            "/api/dispatcher/orders/{$order->id}/assign",
            ['courier_id' => $courier->id]
        )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Only pending orders can be assigned.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'in_transit',
            'courier_id' => null,
        ]);
    }

    public function test_dispatcher_cannot_assign_order_to_non_courier_user(): void
    {
        $dispatcher = $this->createUser('dispatcher');
        $customer = $this->createUser('customer');
        $anotherCustomer = $this->createUser('customer');

        $order = $this->createOrder($customer);

        Sanctum::actingAs($dispatcher);

        $this->postJson(
            "/api/dispatcher/orders/{$order->id}/assign",
            ['courier_id' => $anotherCustomer->id]
        )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Selected user is not a courier.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
            'courier_id' => null,
        ]);
    }

    public function test_dispatcher_can_view_all_orders(): void
    {
        $dispatcher = $this->createUser('dispatcher');
        $customer = $this->createUser('customer');

        $firstOrder = $this->createOrder($customer);
        $secondOrder = $this->createOrder(
            $customer,
            null,
            'delivered'
        );

        Sanctum::actingAs($dispatcher);

        $response = $this->getJson('/api/dispatcher/orders');

        $response->assertOk()
            ->assertJsonPath('message', 'Dispatcher orders retrieved successfully.')
            ->assertJsonCount(2, 'data.data');

        $this->assertEqualsCanonicalizing(
            [$firstOrder->id, $secondOrder->id],
            collect($response->json('data.data'))
                ->pluck('id')
                ->all()
        );
    }

    public function test_dispatcher_can_filter_orders_by_status(): void
    {
        $dispatcher = $this->createUser('dispatcher');
        $customer = $this->createUser('customer');

        $pending = $this->createOrder($customer);
        $this->createOrder($customer, null, 'delivered');

        Sanctum::actingAs($dispatcher);

        $response = $this->getJson(
            '/api/dispatcher/orders?status=pending'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $pending->id)
            ->assertJsonPath('data.data.0.status', 'pending');
    }

    public function test_dispatcher_order_filter_rejects_invalid_status(): void
    {
        $dispatcher = $this->createUser('dispatcher');

        Sanctum::actingAs($dispatcher);

        $this->getJson('/api/dispatcher/orders?status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_dispatcher_can_view_order_detail(): void
    {
        $dispatcher = $this->createUser('dispatcher');
        $customer = $this->createUser('customer');

        $order = $this->createOrder($customer);

        Sanctum::actingAs($dispatcher);

        $this->getJson("/api/dispatcher/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Dispatcher order retrieved successfully.'
            )
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.customer_id', $customer->id);
    }

    public function test_dispatcher_cannot_view_nonexistent_order(): void
    {
        $dispatcher = $this->createUser('dispatcher');

        Sanctum::actingAs($dispatcher);

        $this->getJson('/api/dispatcher/orders/999999')
            ->assertNotFound();
    }

    public function test_customer_cannot_access_dispatcher_orders(): void
    {
        $customer = $this->createUser('customer');

        Sanctum::actingAs($customer);

        $this->getJson('/api/dispatcher/orders')
            ->assertForbidden();
    }

    public function test_admin_can_access_dispatcher_order_endpoints(): void
    {
        $admin = $this->createUser('admin');
        $customer = $this->createUser('customer');

        $order = $this->createOrder($customer);

        Sanctum::actingAs($admin);

        $this->getJson('/api/dispatcher/orders')
            ->assertOk();

        $this->getJson("/api/dispatcher/orders/{$order->id}")
            ->assertOk();
    }
}
