<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;

class DeliveryEtaService
{
    private const ROAD_FACTOR = 1.3;
    private const AVERAGE_SPEED_KMH = 25;
    private const BUFFER_MINUTES = 5;

    public function calculate(Order $order): array
    {
        $unavailable = [
            'available' => false,
            'distance_km' => null,
            'duration_minutes' => null,
            'estimated_arrival_at' => null,
            'message' => 'ETA belum tersedia.',
        ];

        if ($order->status !== 'in_transit') {
            return $unavailable;
        }

        $coordinates = [
            $order->pickup_latitude,
            $order->pickup_longitude,
            $order->delivery_latitude,
            $order->delivery_longitude,
        ];

        if (in_array(null, $coordinates, true)) {
            return [
                ...$unavailable,
                'message' => 'Koordinat pickup atau tujuan belum tersedia.',
            ];
        }

        $distance = $this->haversine(
            (float) $order->pickup_latitude,
            (float) $order->pickup_longitude,
            (float) $order->delivery_latitude,
            (float) $order->delivery_longitude
        );

        if ($distance <= 0) {
            return [
                ...$unavailable,
                'message' => 'Jarak perjalanan tidak valid.',
            ];
        }

        $estimatedRoadDistance = $distance * self::ROAD_FACTOR;

        $travelMinutes = (int) ceil(
            ($estimatedRoadDistance / self::AVERAGE_SPEED_KMH) * 60
        );

        $durationMinutes = $travelMinutes + self::BUFFER_MINUTES;

        $departure = $order->statusHistories
            ->where('new_status', 'in_transit')
            ->sortByDesc('created_at')
            ->first();

        if (!$departure) {
            return $unavailable;
        }

        $arrival = Carbon::parse($departure->created_at)
            ->addMinutes($durationMinutes);

        return [
            'available' => true,
            'distance_km' => round($estimatedRoadDistance, 2),
            'duration_minutes' => $durationMinutes,
            'estimated_arrival_at' => $arrival->toIso8601String(),
            'message' => 'Estimasi berdasarkan perhitungan jarak dan kecepatan rata-rata.',
        ];
    }

    private function haversine(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $earthRadius = 6371;

        $latDifference = deg2rad($latitude2 - $latitude1);
        $lonDifference = deg2rad($longitude2 - $longitude1);

        $a = sin($latDifference / 2) ** 2
            + cos(deg2rad($latitude1))
            * cos(deg2rad($latitude2))
            * sin($lonDifference / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
