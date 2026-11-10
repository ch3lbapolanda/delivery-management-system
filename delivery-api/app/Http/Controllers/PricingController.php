<?php

namespace App\Http\Controllers;

use App\Services\DeliveryPricingService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PricingController extends Controller
{
    public function estimate(
        Request $request,
        DeliveryPricingService $pricingService
    ) {
        $validated = $request->validate([
            'delivery_type' => 'required|in:instant,scheduled',

            'pickup_latitude' => 'required|numeric|between:-90,90',
            'pickup_longitude' => 'required|numeric|between:-180,180',

            'delivery_latitude' => 'required|numeric|between:-90,90',
            'delivery_longitude' => 'required|numeric|between:-180,180',
        ]);

        try {
            $pricing = $pricingService->calculate(
                $validated['delivery_type'],
                (float) $validated['pickup_latitude'],
                (float) $validated['pickup_longitude'],
                (float) $validated['delivery_latitude'],
                (float) $validated['delivery_longitude']
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Delivery price estimated successfully.',
            'data' => $pricing,
        ]);
    }
}