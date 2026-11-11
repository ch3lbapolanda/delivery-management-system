<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
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

    private function createOrder(
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
            'pickup_latitude' => 0,
            'pickup_longitude' => 0,
            'recipient_name' => 'Recipient Test',
            'recipient_phone' => '08198765432',
            'delivery_address' => 'Delivery Test',
            'delivery_latitude' => 0,
            'delivery_longitude' => 0.01,
        ]);
    }

    private function uploadProof(int $orderId)
    {
        return $this->post(
            "/api/courier/orders/{$orderId}/proof",
            [
                'photo' => UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg'),
                'recipient_name' => 'Recipient Test',
                'notes' => 'Package received successfully.',
            ],
            [
                'Accept' => 'application/json',
            ]
        );
    }

    public function test_courier_can_complete_order_status_transitions(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createOrder(
            $customer,
            $courier,
            'assigned'
        );

        Sanctum::actingAs($courier);

        $this->patchJson(
            "/api/courier/orders/{$order->id}/status",
            ['status' => 'picked_up']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'picked_up');

        $this->patchJson(
            "/api/courier/orders/{$order->id}/status",
            ['status' => 'in_transit']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'in_transit')
            ->assertJsonPath('eta.available', true);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'in_transit',
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'old_status' => 'assigned',
            'new_status' => 'picked_up',
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'old_status' => 'picked_up',
            'new_status' => 'in_transit',
        ]);
    }

    public function test_courier_cannot_skip_order_status_transition(): void
    {
        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createOrder(
            $customer,
            $courier,
            'assigned'
        );

        Sanctum::actingAs($courier);

        $response = $this->patchJson(
            "/api/courier/orders/{$order->id}/status",
            ['status' => 'in_transit']
        );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'assigned',
        ]);
    }

    public function test_courier_cannot_update_another_couriers_order(): void
    {
        $customer = $this->createUser('customer');
        $ownerCourier = $this->createUser('courier');
        $otherCourier = $this->createUser('courier');

        $order = $this->createOrder(
            $customer,
            $ownerCourier,
            'assigned'
        );

        Sanctum::actingAs($otherCourier);

        $this->patchJson(
            "/api/courier/orders/{$order->id}/status",
            ['status' => 'picked_up']
        )->assertNotFound();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'assigned',
            'courier_id' => $ownerCourier->id,
        ]);
    }

    public function test_courier_can_upload_proof_and_deliver_order(): void
    {
        Storage::fake('public');

        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createOrder(
            $customer,
            $courier,
            'in_transit'
        );

        Sanctum::actingAs($courier);

        $response = $this->uploadProof($order->id);

        $response->assertCreated()
            ->assertJsonPath(
                'message',
                'Delivery proof uploaded successfully.'
            )
            ->assertJsonPath('data.status', 'delivered');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'delivered',
        ]);

        $this->assertDatabaseHas('delivery_proofs', [
            'order_id' => $order->id,
            'uploaded_by' => $courier->id,
            'recipient_name' => 'Recipient Test',
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'old_status' => 'in_transit',
            'new_status' => 'delivered',
        ]);

        $proof = $order->deliveryProof()->firstOrFail();

        Storage::disk('public')->assertExists(
            $proof->photo_path
        );
    }

    public function test_courier_cannot_upload_proof_before_in_transit(): void
    {
        Storage::fake('public');

        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createOrder(
            $customer,
            $courier,
            'assigned'
        );

        Sanctum::actingAs($courier);

        $response = $this->uploadProof($order->id);

        $response->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Proof can only be uploaded for orders in transit.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'assigned',
        ]);

        $this->assertDatabaseCount('delivery_proofs', 0);

        $this->assertSame(
            [],
            Storage::disk('public')->allFiles('delivery-proofs')
        );
    }

    public function test_courier_cannot_upload_duplicate_delivery_proof(): void
    {
        Storage::fake('public');

        $customer = $this->createUser('customer');
        $courier = $this->createUser('courier');

        $order = $this->createOrder(
            $customer,
            $courier,
            'in_transit'
        );

        Sanctum::actingAs($courier);

        $this->uploadProof($order->id)
            ->assertCreated();

        $this->uploadProof($order->id)
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Delivery proof already exists.',
            ]);

        $this->assertDatabaseCount('delivery_proofs', 1);

        $this->assertCount(
            1,
            Storage::disk('public')->allFiles('delivery-proofs')
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'delivered',
        ]);
    }
}
