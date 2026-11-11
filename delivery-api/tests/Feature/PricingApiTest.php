<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PricingApiTest extends TestCase
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

    private function validPricingPayload(): array
    {
        return [
            'delivery_type' => 'instant',
            'pickup_latitude' => 0,
            'pickup_longitude' => 0,
            'delivery_latitude' => 0,
            'delivery_longitude' => 0.01,
        ];
    }

    public function test_customer_can_estimate_delivery_price(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $response = $this->postJson(
            '/api/pricing/estimate',
            $this->validPricingPayload()
        );

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Delivery price estimated successfully.'
            )
            ->assertJsonPath('data.delivery_type', 'instant')
            ->assertJsonPath('data.base_price', 5000)
            ->assertJsonPath('data.price_per_km', 2000)
            ->assertJsonPath('data.distance_fee', 2900)
            ->assertJsonPath('data.additional_fee', 3000)
            ->assertJsonPath('data.total_price', 10900);
    }

    public function test_guest_cannot_estimate_delivery_price(): void
    {
        $response = $this->postJson(
            '/api/pricing/estimate',
            $this->validPricingPayload()
        );

        $response->assertUnauthorized();
    }

    public function test_pricing_endpoint_rejects_invalid_delivery_type(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $payload = $this->validPricingPayload();
        $payload['delivery_type'] = 'express';

        $response = $this->postJson(
            '/api/pricing/estimate',
            $payload
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_type']);
    }

    public function test_pricing_endpoint_rejects_invalid_coordinates(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $payload = $this->validPricingPayload();
        $payload['pickup_latitude'] = 91;

        $response = $this->postJson(
            '/api/pricing/estimate',
            $payload
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pickup_latitude']);
    }

    public function test_pricing_endpoint_rejects_zero_distance(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $payload = $this->validPricingPayload();

        $payload['delivery_latitude'] = 0;
        $payload['delivery_longitude'] = 0;

        $response = $this->postJson(
            '/api/pricing/estimate',
            $payload
        );

        $response->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Delivery distance must be greater than zero.',
            ]);
    }
}
