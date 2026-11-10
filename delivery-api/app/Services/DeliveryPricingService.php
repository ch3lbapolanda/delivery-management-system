<?php

namespace App\Services;

class DeliveryPricingService
{
    public function calculate(
        string $deliveryType,
        float $pickupLatitude,
        float $pickupLongitude,
        float $deliveryLatitude,
        float $deliveryLongitude
    ): array {
        $config = config("delivery.services.{$deliveryType}");

        if (!$config) {
            throw new \InvalidArgumentException(
                'Invalid delivery type.'
            );
        }

        $straightDistance = $this->haversine(
            $pickupLatitude,
            $pickupLongitude,
            $deliveryLatitude,
            $deliveryLongitude
        );

        $roadFactor = (float) config('delivery.road_factor', 1.3);

        $distance = round($straightDistance * $roadFactor, 2);

        if ($distance <= 0) {
            throw new \InvalidArgumentException(
                'Delivery distance must be greater than zero.'
            );
        }

        $basePrice = (int) $config['base_price'];
        $pricePerKm = (int) $config['price_per_km'];
        $additionalFee = (int) $config['additional_fee'];
        $minimumPrice = (int) $config['minimum_price'];

        $distanceFee = (int) ceil($distance * $pricePerKm);

        $subtotal = $basePrice
            + $distanceFee
            + $additionalFee;

        $totalPrice = max($minimumPrice, $subtotal);

        return [
            'delivery_type' => $deliveryType,
            'distance_km' => $distance,
            'base_price' => $basePrice,
            'price_per_km' => $pricePerKm,
            'distance_fee' => $distanceFee,
            'additional_fee' => $additionalFee,
            'minimum_price' => $minimumPrice,
            'total_price' => $totalPrice,
        ];
    }

    private function haversine(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $earthRadius = 6371;

        $latDifference = deg2rad(
            $latitude2 - $latitude1
        );

        $lonDifference = deg2rad(
            $longitude2 - $longitude1
        );

        $a = sin($latDifference / 2) ** 2
            + cos(deg2rad($latitude1))
            * cos(deg2rad($latitude2))
            * sin($lonDifference / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $c;
    }
}