<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // GET /api/admin/stats
    public function stats(): JsonResponse
    {
        $now = now();

        return response()->json([
            'orders_this_month'  => Order::whereMonth('created_at', $now->month)
                                         ->whereYear('created_at', $now->year)
                                         ->count(),
            'pending_review'     => Order::where('status', 'solicitado')->count(),
            'in_production'      => Order::where('status', 'en_produccion')->count(),
            'ready_for_pickup'   => Order::where('status', 'listo')->count(),
            'revenue_this_month' => Order::whereMonth('created_at', $now->month)
                                         ->whereYear('created_at', $now->year)
                                         ->whereIn('status', ['aceptado','en_produccion','listo','entregado'])
                                         ->sum('quoted_price'),
            'active_clients'     => User::where('role', 'client')
                                         ->whereHas('orders')
                                         ->count(),
            'orders_by_status'   => Order::select('status', DB::raw('count(*) as total'))
                                         ->groupBy('status')
                                         ->pluck('total', 'status'),
        ]);
    }

    // GET /api/admin/clients
    public function clients(): JsonResponse
    {
        $clients = User::where('role', 'client')
            ->withCount('orders')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($clients);
    }
}