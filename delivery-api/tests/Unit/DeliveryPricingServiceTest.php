<?php

namespace Tests\Unit;

use App\Services\DeliveryPricingService;
use InvalidArgumentException;
use Tests\TestCase;

class DeliveryPricingServiceTest extends TestCase
{
    private DeliveryPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DeliveryPricingService();
    }

    public function test_it_calculates_instant_delivery_price(): void
    {
        $result = $this->service->calculate(
            'instant',
            0.0,
            0.0,
            0.0,
            0.01
        );

        $this->assertSame('instant', $result['delivery_type']);
        $this->assertSame(1.45, $result['distance_km']);
        $this->assertSame(5000, $result['base_price']);
        $this->assertSame(2000, $result['price_per_km']);
        $this->assertSame(2900, $result['distance_fee']);
        $this->assertSame(3000, $result['additional_fee']);
        $this->assertSame(10900, $result['total_price']);
    }

    public function test_it_calculates_scheduled_delivery_price(): void
    {
        $result = $this->service->calculate(
            'scheduled',
            0.0,
            0.0,
            0.0,
            0.01
        );

        $this->assertSame('scheduled', $result['delivery_type']);
        $this->assertSame(1.45, $result['distance_km']);
        $this->assertSame(4000, $result['base_price']);
        $this->assertSame(1500, $result['price_per_km']);
        $this->assertSame(2175, $result['distance_fee']);
        $this->assertSame(0, $result['additional_fee']);
        $this->assertSame(8000, $result['minimum_price']);
        $this->assertSame(8000, $result['total_price']);
    }

    public function test_it_applies_minimum_price_for_short_instant_delivery(): void
    {
        $result = $this->service->calculate(
            'instant',
            0.0,
            0.0,
            0.0,
            0.001
        );

        $this->assertSame(0.14, $result['distance_km']);
        $this->assertSame(280, $result['distance_fee']);
        $this->assertSame(10000, $result['total_price']);
    }

    public function test_it_rejects_zero_delivery_distance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Delivery distance must be greater than zero.'
        );

        $this->service->calculate(
            'instant',
            0.0,
            0.0,
            0.0,
            0.0
        );
    }

    public function test_it_rejects_invalid_delivery_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid delivery type.');

        $this->service->calculate(
            'express',
            0.0,
            0.0,
            0.0,
            0.01
        );
    }
}
