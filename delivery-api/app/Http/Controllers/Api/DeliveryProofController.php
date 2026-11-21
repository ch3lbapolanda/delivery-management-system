<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeliveryProofController extends Controller
{
    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $order = Order::with('deliveryProof')
            ->findOrFail($id);

        $role = $user->role?->slug;

        $authorized = match ($role) {
            'admin', 'dispatcher' => true,
            'customer' => $order->customer_id === $user->id,
            'courier' => $order->courier_id === $user->id,
            default => false,
        };

        if (!$authorized) {
            abort(404);
        }

        $proof = $order->deliveryProof;

        if (!$proof || !$proof->photo_path) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (!$disk->exists($proof->photo_path)) {
            abort(404);
        }

        return $disk->response($proof->photo_path);
    }
}

