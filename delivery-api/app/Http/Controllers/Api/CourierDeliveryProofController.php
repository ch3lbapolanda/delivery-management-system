<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\DeliveryProof;
use App\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CourierDeliveryProofController extends Controller
{
    public function store(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'photo' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
            ],
            'recipient_name' => [
                'required',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $photoPath = null;

        try {
            $photoPath = $request->file('photo')
                ->store('delivery-proofs', 'public');

            $order = DB::transaction(function () use (
                $request,
                $id,
                $validated,
                $photoPath
            ) {
                $order = Order::query()
                    ->where('courier_id', $request->user()->id)
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($order->status !== 'in_transit') {
                    abort(
                        422,
                        'Proof can only be uploaded for orders in transit.'
                    );
                }

                if ($order->deliveryProof()->exists()) {
                    abort(422, 'Delivery proof already exists.');
                }

                $oldStatus = $order->status;

                DeliveryProof::create([
                    'order_id' => $order->id,
                    'uploaded_by' => $request->user()->id,
                    'photo_path' => $photoPath,
                    'recipient_name' => $validated['recipient_name'],
                    'notes' => $validated['notes'] ?? null,
                ]);

                $order->status = 'delivered';
                $order->save();

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'changed_by' => $request->user()->id,
                    'old_status' => $oldStatus,
                    'new_status' => 'delivered',
                    'note' => 'Delivery proof uploaded successfully.',
                ]);

                return $order;
            });
        } catch (Throwable $exception) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }

        $order->load([
            'customer:id,name,email',
            'statusHistories.changedBy:id,name',
            'deliveryProof.uploadedBy:id,name',
        ]);

        return response()->json([
            'message' => 'Delivery proof uploaded successfully.',
            'data' => $order,
        ], 201);
    }
}

