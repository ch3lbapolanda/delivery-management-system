<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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

        $totalOrders = Order::count();

        $orderStats = [];

        foreach ($statuses as $status) {
            $orderStats[$status] = Order::where(
                'status',
                $status
            )->count();
        }

        $activeOrders =
            $orderStats['assigned'] +
            $orderStats['picked_up'] +
            $orderStats['in_transit'];

        $successRate = $totalOrders > 0
            ? round(
                ($orderStats['delivered'] / $totalOrders) * 100,
                1
            )
            : 0;

        $cancellationRate = $totalOrders > 0
            ? round(
                ($orderStats['cancelled'] / $totalOrders) * 100,
                1
            )
            : 0;

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

        // Daily orders for the last 7 days.
        $startDate = now()->startOfDay()->subDays(6);

        $dailyCounts = Order::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'date');

        $dailyOrders = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->startOfDay()->subDays($i);
            $dateKey = $date->format('Y-m-d');

            $dailyOrders[] = [
                'date' => $dateKey,
                'label' => $date->format('d M'),
                'total' => (int) ($dailyCounts->get($dateKey) ?? 0),
            ];
        }

        // Calculate delivery duration per completed order.
        $deliveryDurations = DB::table(
            'order_status_histories as transit'
        )
            ->join(
                'order_status_histories as delivered',
                'transit.order_id',
                '=',
                'delivered.order_id'
            )
            ->whereColumn(
                'transit.order_id',
                'delivered.order_id'
            )
            ->where('transit.new_status', 'in_transit')
            ->where('delivered.new_status', 'delivered')
            ->whereRaw(
                'delivered.created_at >= transit.created_at'
            )
            ->selectRaw(
                'transit.order_id,
                TIMESTAMPDIFF(
                    MINUTE,
                    MIN(transit.created_at),
                    MIN(delivered.created_at)
                ) as duration_minutes'
            )
            ->groupBy('transit.order_id')
            ->get();

        $averageDeliveryDuration = $deliveryDurations->isNotEmpty()
            ? round(
                (float) $deliveryDurations->avg('duration_minutes'),
                1
            )
            : null;

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
                    'total' => $totalOrders,
                    'by_status' => $orderStats,
                ],

                'users' => $userStats,

                'operational_statistics' => [
                    'success_rate' => $successRate,
                    'cancellation_rate' => $cancellationRate,
                    'active_orders' => $activeOrders,
                    'average_delivery_duration_minutes' => $averageDeliveryDuration,
                    'completed_orders' => $orderStats['delivered'],
                    'daily_orders' => $dailyOrders,
                ],

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

