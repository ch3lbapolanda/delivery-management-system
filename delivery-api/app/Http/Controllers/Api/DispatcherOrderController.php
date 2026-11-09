<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DeliveryEtaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispatcherOrderController extends Controller
{
    public function index(
        Request $request,
        DeliveryEtaService $etaService
    ): JsonResponse {
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
                'statusHistories',
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

        $orders->getCollection()->transform(
            function ($order) use ($etaService) {
                $order->setAttribute(
                    'eta',
                    $etaService->calculate($order)
                );

                return $order;
            }
        );

        return response()->json([
            'message' => 'Dispatcher orders retrieved successfully.',
            'data' => $orders,
        ]);
    }

    public function show(
        int $id,
        DeliveryEtaService $etaService
    ): JsonResponse {
        $order = Order::with([
            'customer:id,name,email',
            'courier:id,name',
            'statusHistories.changedBy:id,name',
        ])->findOrFail($id);

        return response()->json([
            'message' => 'Dispatcher order retrieved successfully.',
            'data' => $order,
            'eta' => $etaService->calculate($order),
        ]);
    }
}

