<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispatcherOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'sometimes',
                'string',
                'in:pending,assigned,picked_up,in_transit,delivered,cancelled',
            ],
        ]);

        $orders = Order::query()
            ->with([
                'customer:id,name,email',
                'courier:id,name',
            ])
            ->when(
                isset($validated['status']),
                fn ($query) => $query->where(
                    'status',
                    $validated['status']
                )
            )
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Dispatcher orders retrieved successfully.',
            'data' => $orders,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $order = Order::with([
            'customer:id,name,email',
            'courier:id,name',
            'statusHistories.changedBy:id,name',
        ])->findOrFail($id);

        return response()->json([
            'message' => 'Dispatcher order retrieved successfully.',
            'data' => $order,
        ]);
    }
}
