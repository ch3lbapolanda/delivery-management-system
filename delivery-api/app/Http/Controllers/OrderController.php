<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'delivery_type' => 'required|in:instant,scheduled',

            'sender_name' => 'required|string|max:255',
            'sender_phone' => 'required|string|max:30',

            'pickup_address' => 'required|string',
            'pickup_latitude' => 'nullable|numeric|between:-90,90',
            'pickup_longitude' => 'nullable|numeric|between:-180,180',

            'recipient_name' => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:30',

            'delivery_address' => 'required|string',
            'delivery_latitude' => 'nullable|numeric|between:-90,90',
            'delivery_longitude' => 'nullable|numeric|between:-180,180',

            'scheduled_pickup_at' => [
                'nullable',
                'required_if:delivery_type,scheduled',
                'prohibited_if:delivery_type,instant',
                'date',
                'after:now',
            ],

            'notes' => 'nullable|string',
        ]);

        $order = DB::transaction(function () use ($validated, $request) {
            $order = Order::create([
                ...$validated,

                'order_number' => 'KM-' .
                    now()->format('Ymd') . '-' .
                    strtoupper(Str::random(6)),

                'customer_id' => $request->user()->id,
                'status' => 'pending',
            ]);

            $order->statusHistories()->create([
                'changed_by' => $request->user()->id,
                'old_status' => null,
                'new_status' => 'pending',
                'note' => 'Order created by customer.',
            ]);

            return $order;
        });

        return response()->json([
            'message' => 'Order created successfully.',
            'data' => $order->load('statusHistories'),
        ], 201);
    }
  
    public function index(Request $request)
    {
        $orders = $request->user()
            ->customerOrders()
            ->latest()
            ->paginate(10);
    
        return response()->json([
            'message' => 'Orders retrieved successfully.',
            'data' => $orders,
        ]);
    }
    
    public function show(Request $request, int $id)
    {
        $order = $request->user()
            ->customerOrders()
            ->with('statusHistories')
            ->findOrFail($id);
    
        return response()->json([
            'message' => 'Order retrieved successfully.',
            'data' => $order,
        ]);
    }

}

