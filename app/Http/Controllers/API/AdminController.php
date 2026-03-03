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
    /**
 * @OA\Get(
 *     path="/api/admin/stats",
 *     tags={"Admin"},
 *     summary="Estadísticas del dashboard (solo admin)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Estadísticas generales del taller",
 *         @OA\JsonContent(
 *             @OA\Property(property="orders_this_month", type="integer", example=12),
 *             @OA\Property(property="pending_review", type="integer", example=3),
 *             @OA\Property(property="in_production", type="integer", example=5),
 *             @OA\Property(property="ready_for_pickup", type="integer", example=2),
 *             @OA\Property(property="revenue_this_month", type="number", example=8500.00),
 *             @OA\Property(property="active_clients", type="integer", example=7),
 *             @OA\Property(property="orders_by_status", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(response=403, description="Solo admin")
 * )
 */
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
    /**
 * @OA\Get(
 *     path="/api/admin/clients",
 *     tags={"Admin"},
 *     summary="Listar clientes registrados (solo admin)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Lista de clientes con conteo de pedidos",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="name", type="string"),
 *                 @OA\Property(property="email", type="string"),
 *                 @OA\Property(property="phone", type="string"),
 *                 @OA\Property(property="orders_count", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=403, description="Solo admin")
 * )
 */
    public function clients(): JsonResponse
    {
        $clients = User::where('role', 'client')
            ->withCount('orders')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($clients);
    }
}