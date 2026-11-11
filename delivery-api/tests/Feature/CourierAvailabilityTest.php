<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierAvailabilityTest extends TestCase
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
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'availability_status' => 'available',
        ]);

        $user->save();

        return $user;
    }

    private function createActiveOrder(
        User $customer,
        User $courier,
        string $status = 'assigned'
    ): Order {
        return Order::create([
            'order_number' => 'KM-' . strtoupper(uniqid()),
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
            'delivery_type' => 'instant',
            'status' => $status,
            'sender_name' => 'Sender Test',
            'sender_phone' => '08123456789',
            'pickup_address' => 'Pickup Test',
            'recipient_name' => 'Recipient Test',
            'recipient_phone' => '08123456789',
            'delivery_address' => 'Delivery Test',
        ]);
    }

    public function test_courier_can_view_own_availability(): void
    {
        $courier = $this->createUser('courier');

        Sanctum::actingAs($courier);

        $response = $this->getJson('/api/courier/availability');

        $response->assertOk()
            ->assertJsonPath('data.availability_status', 'available')
            ->assertJsonPath('data.work_status', 'available')
            ->assertJsonPath('data.has_active_order', false);
    }

    public function test_courier_can_change_availability_to_offline(): void
    {
        $courier = $this->createUser('courier');

        Sanctum::actingAs($courier);

        $response = $this->patchJson('/api/courier/availability', [
            'availability_status' => 'offline',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.availability_status', 'offline')
            ->assertJsonPath('data.work_status', 'offline');

        $this->assertDatabaseHas('users', [
            'id' => $courier->id,
            'availability_status' => 'offline',
        ]);
    }

    public function test_courier_cannot_go_offline_with_active_order(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $this->createActiveOrder($customer, $courier);

        Sanctum::actingAs($courier);

        $response = $this->patchJson('/api/courier/availability', [
            'availability_status' => 'offline',
        ]);

        $response->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'You cannot go offline while an active order exists.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $courier->id,
            'availability_status' => 'available',
        ]);
    }

    public function test_dispatcher_can_view_courier_availability_summary(): void
    {
        $dispatcher = $this->createUser('dispatcher');
        $customer = $this->createUser('customer');

        $this->createUser('courier');

        $offline = $this->createUser('courier');
        $offline->availability_status = 'offline';
        $offline->save();

        $busy = $this->createUser('courier');
        $order = $this->createActiveOrder($customer, $busy);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'courier_id' => $busy->id,
            'status' => 'assigned',
        ]);

        $this->assertTrue($busy->fresh()->hasActiveOrder());

        Sanctum::actingAs($dispatcher);

        $response = $this->getJson('/api/dispatcher/courier-availability');

        $response->assertOk()
            ->assertJsonPath('data.summary.available', 1)
            ->assertJsonPath('data.summary.busy', 1)
            ->assertJsonPath('data.summary.offline', 1)
            ->assertJsonPath('data.summary.total', 3);
    }

    public function test_dispatcher_cannot_assign_order_to_offline_courier(): void
    {
        $dispatcher = $this->createUser('dispatcher');
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $courier->availability_status = 'offline';
        $courier->save();

        $order = $this->createActiveOrder(
            $customer,
            $courier,
            'pending'
        );

        $order->courier_id = null;
        $order->save();

        Sanctum::actingAs($dispatcher);

        $response = $this->postJson(
            "/api/dispatcher/orders/{$order->id}/assign",
            ['courier_id' => $courier->id]
        );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
            'courier_id' => null,
        ]);
    }

    public function test_dispatcher_cannot_assign_order_to_busy_courier(): void
    {
        $dispatcher = $this->createUser('dispatcher');
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $this->createActiveOrder($customer, $courier, 'assigned');

        $pendingOrder = $this->createActiveOrder(
            $customer,
            $courier,
            'pending'
        );

        $pendingOrder->courier_id = null;
        $pendingOrder->save();

        Sanctum::actingAs($dispatcher);

        $response = $this->postJson(
            "/api/dispatcher/orders/{$pendingOrder->id}/assign",
            ['courier_id' => $courier->id]
        );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $pendingOrder->id,
            'status' => 'pending',
            'courier_id' => null,
        ]);
    }

    public function test_courier_cannot_access_dispatcher_availability_endpoint(): void
    {
        $courier = $this->createUser('courier');

        Sanctum::actingAs($courier);

        $response = $this->getJson(
            '/api/dispatcher/courier-availability'
        );

        $response->assertForbidden();
    }
}
