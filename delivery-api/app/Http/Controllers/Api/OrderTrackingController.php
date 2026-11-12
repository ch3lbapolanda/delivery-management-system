<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    public function customerLocation(Request $request, int $id)
    {
        $order = $request->user()
            ->customerOrders()
            ->whereKey($id)
            ->firstOrFail();

        return $this->locationResponse($order);
    }

    public function dispatcherLocation(int $id)
    {
        $order = Order::findOrFail($id);

        return $this->locationResponse($order);
    }

    private function locationResponse(Order $order)
    {
        if (!$order->courier_id || $order->status !== 'in_transit') {
            return response()->json([
                'message' => 'Courier location is not available.',
                'data' => null,
            ]);
        }

        $location = $order->courierLocations()
            ->where('courier_id', $order->courier_id)
            ->latest('recorded_at')
            ->first();

        if (!$location) {
            return response()->json([
                'message' => 'No courier location recorded yet.',
                'data' => null,
            ]);
        }

        return response()->json([
            'message' => 'Courier location retrieved successfully.',
            'data' => [
                'order_id' => $location->order_id,
                'courier_id' => $location->courier_id,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'accuracy' => $location->accuracy,
                'heading' => $location->heading,
                'speed' => $location->speed,
                'recorded_at' => $location->recorded_at,
            ],
        ]);
    }
}
