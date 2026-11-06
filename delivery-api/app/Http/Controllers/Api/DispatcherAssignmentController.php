<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispatcherAssignmentController extends Controller
{
    public function couriers(): JsonResponse
    {
        $couriers = User::query()
            ->whereHas('role', function ($query) {
                $query->where('slug', 'courier');
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Courier list retrieved successfully.',
            'data' => $couriers,
        ]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'courier_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $result = DB::transaction(function () use ($request, $id, $validated) {
            $order = Order::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== 'pending') {
                return [
                    'error' => true,
                    'message' => 'Only pending orders can be assigned.',
                ];
            }

            $courier = User::with('role')
                ->findOrFail($validated['courier_id']);

            if (!$courier->role || $courier->role->slug !== 'courier') {
                return [
                    'error' => true,
                    'message' => 'Selected user is not a courier.',
                ];
            }

            $oldStatus = $order->status;

            $order->courier_id = $courier->id;
            $order->status = 'assigned';
            $order->save();

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'changed_by' => $request->user()->id,
                'old_status' => $oldStatus,
                'new_status' => 'assigned',
                'note' => 'Courier assigned: ' . $courier->name,
            ]);

            return [
                'error' => false,
                'order' => $order->load([
                    'customer:id,name,email',
                    'courier:id,name',
                    'statusHistories.changedBy:id,name',
                ]),
            ];
        });

        if ($result['error']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => 'Courier assigned successfully.',
            'data' => $result['order'],
        ]);
    }
}
