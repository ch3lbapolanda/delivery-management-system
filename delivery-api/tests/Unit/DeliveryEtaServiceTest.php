<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\DeliveryEtaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeliveryEtaServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryEtaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DeliveryEtaService();
    }

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

    private function createOrder(
        string $status,
        ?float $pickupLatitude = 0,
        ?float $pickupLongitude = 0,
        ?float $deliveryLatitude = 0,
        ?float $deliveryLongitude = 0.01
    ): Order {
        $customer = $this->createCustomer();

        return Order::create([
            'order_number' => 'KM-' . strtoupper(uniqid()),
            'customer_id' => $customer->id,
            'delivery_type' => 'instant',
            'status' => $status,
            'sender_name' => 'Sender Test',
            'sender_phone' => '08123456789',
            'pickup_address' => 'Pickup Test',
            'pickup_latitude' => $pickupLatitude,
            'pickup_longitude' => $pickupLongitude,
            'recipient_name' => 'Recipient Test',
            'recipient_phone' => '08198765432',
            'delivery_address' => 'Delivery Test',
            'delivery_latitude' => $deliveryLatitude,
            'delivery_longitude' => $deliveryLongitude,
        ]);
    }

    private function addInTransitHistory(Order $order): void
    {
        $history = $order->statusHistories()->create([
            'changed_by' => null,
            'old_status' => 'picked_up',
            'new_status' => 'in_transit',
            'note' => 'Courier started delivery.',
        ]);

        $timestamp = Carbon::parse('2026-11-11 10:00:00');

        $history->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->save();
    }

    public function test_it_calculates_eta_for_in_transit_order(): void
    {
        $order = $this->createOrder('in_transit');

        $this->addInTransitHistory($order);

        $order->load('statusHistories');

        $result = $this->service->calculate($order);

        $this->assertTrue($result['available']);
        $this->assertSame(1.45, $result['distance_km']);
        $this->assertSame(9, $result['duration_minutes']);

        $departure = $order->statusHistories
            ->where('new_status', 'in_transit')
            ->first();

        $expectedArrival = Carbon::parse($departure->created_at)
            ->addMinutes(9);

        $actualArrival = Carbon::parse(
            $result['estimated_arrival_at']
        );

        $this->assertTrue(
            $expectedArrival->equalTo($actualArrival)
        );
    }

    public function test_eta_is_unavailable_for_pending_order(): void
    {
        $order = $this->createOrder('pending');

        $result = $this->service->calculate($order);

        $this->assertFalse($result['available']);
        $this->assertNull($result['distance_km']);
        $this->assertNull($result['duration_minutes']);
        $this->assertNull($result['estimated_arrival_at']);
    }

    public function test_eta_is_unavailable_when_coordinates_are_missing(): void
    {
        $order = $this->createOrder(
            'in_transit',
            null,
            null,
            0,
            0.01
        );

        $this->addInTransitHistory($order);

        $result = $this->service->calculate(
            $order->load('statusHistories')
        );

        $this->assertFalse($result['available']);
        $this->assertSame(
            'Koordinat pickup atau tujuan belum tersedia.',
            $result['message']
        );
    }

    public function test_eta_is_unavailable_when_distance_is_zero(): void
    {
        $order = $this->createOrder(
            'in_transit',
            0,
            0,
            0,
            0
        );

        $this->addInTransitHistory($order);

        $result = $this->service->calculate(
            $order->load('statusHistories')
        );

        $this->assertFalse($result['available']);
        $this->assertSame(
            'Jarak perjalanan tidak valid.',
            $result['message']
        );
    }

    public function test_eta_is_unavailable_for_delivered_order(): void
    {
        $order = $this->createOrder('delivered');

        $this->addInTransitHistory($order);

        $result = $this->service->calculate(
            $order->load('statusHistories')
        );

        $this->assertFalse($result['available']);
        $this->assertNull($result['estimated_arrival_at']);
    }
}
