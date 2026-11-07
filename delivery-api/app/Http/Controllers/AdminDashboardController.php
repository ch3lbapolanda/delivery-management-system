<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $statuses = [
            'pending',
            'assigned',
            'picked_up',
            'in_transit',
            'delivered',
            'cancelled',
        ];

        $orderStats = [];

        foreach ($statuses as $status) {
            $orderStats[$status] = Order::where('status', $status)->count();
        }

        $userStats = [
            'total' => User::count(),
            'customers' => User::whereHas('role', function ($query) {
                $query->where('slug', 'customer');
            })->count(),
            'dispatchers' => User::whereHas('role', function ($query) {
                $query->where('slug', 'dispatcher');
            })->count(),
            'couriers' => User::whereHas('role', function ($query) {
                $query->where('slug', 'courier');
            })->count(),
            'admins' => User::whereHas('role', function ($query) {
                $query->where('slug', 'admin');
            })->count(),
        ];

        $recentOrders = Order::with([
            'customer:id,name,email',
            'courier:id,name,email',
        ])
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'message' => 'Admin dashboard data retrieved successfully',
            'data' => [
                'orders' => [
                    'total' => Order::count(),
                    'by_status' => $orderStats,
                ],
                'users' => $userStats,
                'recent_orders' => $recentOrders,
            ],
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'nullable',
                'string',
                'in:all,pending,assigned,picked_up,in_transit,delivered,cancelled,in_progress',
            ],
        ]);

        $query = Order::with([
            'customer:id,name,email',
            'courier:id,name,email',
        ])->latest();

        $status = $validated['status'] ?? 'all';

        if ($status === 'in_progress') {
            $query->whereIn('status', [
                'assigned',
                'picked_up',
                'in_transit',
            ]);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json([
            'message' => 'Admin orders retrieved successfully',
            'data' => $query->paginate(10),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => [
                'nullable',
                'string',
                'in:all,admin,dispatcher,courier,customer',
            ],
        ]);

        $query = User::with('role')
            ->select('id', 'name', 'email', 'role_id', 'created_at')
            ->latest();

        $role = $validated['role'] ?? 'all';

        if ($role !== 'all') {
            $query->whereHas('role', function ($builder) use ($role) {
                $builder->where('slug', $role);
            });
        }

        return response()->json([
            'message' => 'Admin users retrieved successfully',
            'data' => $query->paginate(10),
        ]);
    }
}
