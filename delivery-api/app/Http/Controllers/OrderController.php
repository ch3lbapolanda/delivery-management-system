<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\DeliveryEtaService;
use App\Services\DeliveryPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function store(
        Request $request,
        DeliveryEtaService $etaService,
        DeliveryPricingService $pricingService
    ) {
        $validated = $request->validate([
            'delivery_type' => 'required|in:instant,scheduled',

            'sender_name' => 'required|string|max:255',
            'sender_phone' => 'required|string|max:30',

            'pickup_address' => 'required|string',
            'pickup_latitude' => 'required|numeric|between:-90,90',
            'pickup_longitude' => 'required|numeric|between:-180,180',

            'recipient_name' => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:30',

            'delivery_address' => 'required|string',
            'delivery_latitude' => 'required|numeric|between:-90,90',
            'delivery_longitude' => 'required|numeric|between:-180,180',

            'scheduled_pickup_at' => [
                'nullable',
                'required_if:delivery_type,scheduled',
                'prohibited_if:delivery_type,instant',
                'date',
                'after:now',
            ],

            'notes' => 'nullable|string',
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

        $order = DB::transaction(function () use (
            $validated,
            $request,
            $pricing
        ) {
            $order = Order::create([
                ...$validated,

                'order_number' => 'KM-' .
                    now()->format('Ymd') . '-' .
                    strtoupper(Str::random(6)),

                'customer_id' => $request->user()->id,
                'status' => 'pending',
                'price' => $pricing['total_price'],
                'pricing_details' => $pricing,
            ]);

            $order->statusHistories()->create([
                'changed_by' => $request->user()->id,
                'old_status' => null,
                'new_status' => 'pending',
                'note' => 'Order created by customer.',
            ]);

            return $order;
        });

        $order->load('statusHistories');

        return response()->json([
            'message' => 'Order created successfully.',
            'data' => $order,
            'pricing' => $pricing,
            'eta' => $etaService->calculate($order),
        ], 201);
    }

    public function index(
        Request $request,
        DeliveryEtaService $etaService
    ) {
        $orders = $request->user()
            ->customerOrders()
            ->with([
                'statusHistories',
                'deliveryProof.uploadedBy:id,name',
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
            'message' => 'Orders retrieved successfully.',
            'data' => $orders,
        ]);
    }

    public function cancel(
        Request $request,
        int $id,
        DeliveryEtaService $etaService
    ) {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        $order = DB::transaction(function () use (
            $request,
            $id,
            $validated
        ) {
            $order = $request->user()
                ->customerOrders()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($order->status, ['pending', 'assigned'])) {
                abort(422, 'This order cannot be cancelled.');
            }

            $oldStatus = $order->status;

            $order->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['cancellation_reason'],
            ]);

            $order->statusHistories()->create([
                'changed_by' => $request->user()->id,
                'old_status' => $oldStatus,
                'new_status' => 'cancelled',
                'note' => $validated['cancellation_reason'],
            ]);

            return $order->load('statusHistories');
        });

        return response()->json([
            'message' => 'Order cancelled successfully.',
            'data' => $order,
            'eta' => $etaService->calculate($order),
        ]);
    }

    public function show(
        Request $request,
        int $id,
        DeliveryEtaService $etaService
    ) {
        $order = $request->user()
            ->customerOrders()
            ->with([
                'statusHistories',
                'deliveryProof.uploadedBy:id,name',
            ])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Order retrieved successfully.',
            'data' => $order,
            'eta' => $etaService->calculate($order),
        ]);
    }
}

