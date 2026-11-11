<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourierAvailabilityController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $courier = $request->user();

        return response()->json([
            'message' => 'Availability retrieved successfully.',
            'data' => [
                'availability_status' => $courier->availability_status,
                'work_status' => $courier->hasActiveOrder()
                    ? 'busy'
                    : $courier->availability_status,
                'has_active_order' => $courier->hasActiveOrder(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'availability_status' => [
                'required',
                'string',
                'in:available,offline',
            ],
        ]);

        $courier = $request->user();

        if (
            $validated['availability_status'] === 'offline'
            && $courier->hasActiveOrder()
        ) {
            return response()->json([
                'message' => 'You cannot go offline while an active order exists.',
            ], 422);
        }

        $courier->availability_status =
            $validated['availability_status'];

        $courier->save();

        return response()->json([
            'message' => 'Availability updated successfully.',
            'data' => [
                'availability_status' => $courier->availability_status,
                'work_status' => $courier->hasActiveOrder()
                    ? 'busy'
                    : $courier->availability_status,
                'has_active_order' => $courier->hasActiveOrder(),
            ],
        ]);
    }
}
