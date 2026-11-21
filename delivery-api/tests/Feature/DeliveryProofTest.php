<?php

namespace Tests\Feature;

use App\Models\DeliveryProof;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryProofTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role): User
    {
        $roleModel = Role::firstOrCreate(
            ['slug' => $role],
            [
                'name' => ucfirst($role),
                'description' => 'Test role',
            ]
        );

        $user = new User();

        $user->forceFill([
            'name' => ucfirst($role) . ' Test',
            'email' => uniqid() . '@test.com',
            'password' => 'password',
            'role_id' => $roleModel->id,
            'availability_status' => 'available',
            'is_active' => true,
        ]);

        $user->save();

        return $user;
    }

    private function createProofOrder(
        User $customer,
        User $courier
    ): Order {
        $order = Order::create([
            'order_number' => 'TEST-' . uniqid(),
            'customer_id' => $customer->id,
            'courier_id' => $courier->id,
            'delivery_type' => 'instant',
            'status' => 'delivered',
            'sender_name' => 'Sender',
            'sender_phone' => '08123456789',
            'pickup_address' => 'Pickup address',
            'pickup_latitude' => 0,
            'pickup_longitude' => 0,
            'recipient_name' => 'Recipient',
            'recipient_phone' => '08198765432',
            'delivery_address' => 'Delivery address',
            'delivery_latitude' => 0,
            'delivery_longitude' => 0.01,
        ]);

        Storage::disk('local')->put(
            'delivery-proofs/test-proof.jpg',
            'fake-image-content'
        );

        DeliveryProof::create([
            'order_id' => $order->id,
            'uploaded_by' => $courier->id,
            'photo_path' => 'delivery-proofs/test-proof.jpg',
            'recipient_name' => 'Recipient',
            'notes' => 'Test proof',
        ]);

        return $order;
    }

    private function assertCanViewPhoto(
        User $user,
        Order $order
    ): void {
        Sanctum::actingAs($user);

        $this->get(
            "/api/orders/{$order->id}/proof/photo"
        )->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_admin_can_view_delivery_proof(): void
    {
        Storage::fake('local');

        $order = $this->createProofOrder(
            $this->createUser('customer'),
            $this->createUser('courier')
        );

        $this->assertCanViewPhoto(
            $this->createUser('admin'),
            $order
        );
    }

    public function test_dispatcher_can_view_delivery_proof(): void
    {
        Storage::fake('local');

        $order = $this->createProofOrder(
            $this->createUser('customer'),
            $this->createUser('courier')
        );

        $this->assertCanViewPhoto(
            $this->createUser('dispatcher'),
            $order
        );
    }

    public function test_order_customer_can_view_delivery_proof(): void
    {
        Storage::fake('local');

        $customer = $this->createUser('customer');

        $order = $this->createProofOrder(
            $customer,
            $this->createUser('courier')
        );

        $this->assertCanViewPhoto($customer, $order);
    }

    public function test_assigned_courier_can_view_delivery_proof(): void
    {
        Storage::fake('local');

        $courier = $this->createUser('courier');

        $order = $this->createProofOrder(
            $this->createUser('customer'),
            $courier
        );

        $this->assertCanViewPhoto($courier, $order);
    }

    public function test_other_customer_cannot_view_delivery_proof(): void
    {
        Storage::fake('local');

        $order = $this->createProofOrder(
            $this->createUser('customer'),
            $this->createUser('courier')
        );

        Sanctum::actingAs($this->createUser('customer'));

        $this->get(
            "/api/orders/{$order->id}/proof/photo"
        )->assertNotFound();
    }

    public function test_other_courier_cannot_view_delivery_proof(): void
    {
        Storage::fake('local');

        $order = $this->createProofOrder(
            $this->createUser('customer'),
            $this->createUser('courier')
        );

        Sanctum::actingAs($this->createUser('courier'));

        $this->get(
            "/api/orders/{$order->id}/proof/photo"
        )->assertNotFound();
    }

    public function test_guest_cannot_view_delivery_proof(): void
    {
        Storage::fake('local');

        $order = $this->createProofOrder(
            $this->createUser('customer'),
            $this->createUser('courier')
        );

        $this->get(
            "/api/orders/{$order->id}/proof/photo"
        )->assertUnauthorized();
    }
}
