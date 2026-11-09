<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\DeliveryEtaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourierOrderController extends Controller
{
    public function index(
        Request $request,
        DeliveryEtaService $etaService
    ): JsonResponse {
        $orders = Order::query()
            ->where('courier_id', $request->user()->id)
            ->with([
                'customer:id,name,email',
                'statusHistories.changedBy:id,name',
            ])
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
            'message' => 'Courier orders retrieved successfully.',
            'data' => $orders,
        ]);
    }

    public function show(
        Request $request,
        int $id,
        DeliveryEtaService $etaService
    ): JsonResponse {
        $order = Order::query()
            ->where('courier_id', $request->user()->id)
            ->with([
                'customer:id,name,email',
                'statusHistories.changedBy:id,name',
            ])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Courier order retrieved successfully.',
            'data' => $order,
            'eta' => $etaService->calculate($order),
        ]);
    }

    public function updateStatus(
        Request $request,
        int $id,
        DeliveryEtaService $etaService
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:picked_up,in_transit,delivered',
            ],
            'note' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $order = DB::transaction(function () use (
            $request,
            $id,
            $validated
        ) {
            $order = Order::query()
                ->where('courier_id', $request->user()->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $allowedTransitions = [
                'assigned' => 'picked_up',
                'picked_up' => 'in_transit',
                'in_transit' => 'delivered',
            ];

            $nextStatus = $allowedTransitions[$order->status] ?? null;

            if ($nextStatus !== $validated['status']) {
                abort(
                    422,
                    'Invalid status transition. Current status: ' . $order->status
                );
            }

            $oldStatus = $order->status;

            $order->status = $validated['status'];
            $order->save();

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'changed_by' => $request->user()->id,
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'note' => $validated['note']
                    ?? 'Courier updated delivery status.',
            ]);

            return $order;
        });

        $order->load([
            'customer:id,name,email',
            'statusHistories.changedBy:id,name',
        ]);

        return response()->json([
            'message' => 'Order status updated successfully.',
            'data' => $order,
            'eta' => $etaService->calculate($order),
        ]);
    }
}


