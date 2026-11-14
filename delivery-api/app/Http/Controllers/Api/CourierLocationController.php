<?php

namespace App\Http\Controllers\Api;

use App\Events\CourierLocationUpdated;
use App\Http\Controllers\Controller;
use App\Models\CourierLocation;
use App\Models\Order;
use Illuminate\Http\Request;

class CourierLocationController extends Controller
{
    public function store(Request $request, string $id)
    {
        $validated = $request->validate([
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
            'accuracy' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100000',
            ],
            'heading' => [
                'nullable',
                'numeric',
                'between:0,360',
            ],
            'speed' => [
                'nullable',
                'numeric',
                'min:0',
                'max:1000',
            ],
            'recorded_at' => [
                'required',
                'date',
            ],
        ]);

        $courier = $request->user();

        $order = Order::query()
            ->where('id', $id)
            ->where('courier_id', $courier->id)
            ->where('status', 'in_transit')
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found or not available for tracking.',
            ], 404);
        }

        // Ambil lokasi GPS terakhir sebelum menyimpan titik baru.
        $latestLocation = CourierLocation::query()
            ->where('order_id', $order->id)
            ->where('courier_id', $courier->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        // Simpan semua titik GPS sebagai riwayat.
        $location = CourierLocation::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'] ?? null,
            'heading' => $validated['heading'] ?? null,
            'speed' => $validated['speed'] ?? null,
            'recorded_at' => $validated['recorded_at'],
        ]);

        // Broadcast hanya jika timestamp lebih baru.
        $isLatestLocation = !$latestLocation
            || $location->recorded_at->gt($latestLocation->recorded_at);

        if ($isLatestLocation) {
            CourierLocationUpdated::dispatch(
                orderId: (int) $order->id,
                latitude: (float) $location->latitude,
                longitude: (float) $location->longitude,
                accuracy: $location->accuracy !== null
                    ? (float) $location->accuracy
                    : null,
                heading: $location->heading !== null
                    ? (float) $location->heading
                    : null,
                speed: $location->speed !== null
                    ? (float) $location->speed
                    : null,
                recordedAt: $location->recorded_at->toISOString(),
            );
        }

        return response()->json([
            'message' => 'Courier location recorded successfully.',
            'data' => $location,
        ], 201);
    }
}

