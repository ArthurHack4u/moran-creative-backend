<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Finish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinishController extends Controller
{
    /**
 * @OA\Get(
 *     path="/api/finishes",
 *     tags={"Acabados"},
 *     summary="Listar acabados disponibles (público)",
 *     @OA\Response(response=200, description="Lista de acabados con su costo")
 * )
 */
    public function index(): JsonResponse
    {
        return response()->json(Finish::all());
    }
    /**
 * @OA\Post(
 *     path="/api/finishes",
 *     tags={"Acabados - Admin"},
 *     summary="Crear acabado (solo admin)",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name","fixed_cost"},
 *             @OA\Property(property="name", type="string", example="Lijado fino"),
 *             @OA\Property(property="description", type="string", example="Superficie suave al tacto"),
 *             @OA\Property(property="fixed_cost", type="number", example=50.00)
 *         )
 *     ),
 *     @OA\Response(response=201, description="Acabado creado"),
 *     @OA\Response(response=422, description="Error de validación")
 * )
 */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:80|unique:finishes,name',
            'description' => 'nullable|string',
            'fixed_cost'  => 'required|numeric|min:0',
        ]);

        return response()->json(Finish::create($data), 201);
    }

/**
 * @OA\Put(
 *     path="/api/finishes/{id}",
 *     tags={"Acabados - Admin"},
 *     summary="Actualizar acabado (solo admin)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", example="Lijado medio"),
 *             @OA\Property(property="description", type="string"),
 *             @OA\Property(property="fixed_cost", type="number", example=40.00)
 *         )
 *     ),
 *     @OA\Response(response=200, description="Acabado actualizado"),
 *     @OA\Response(response=404, description="Acabado no encontrado")
 * )
 */
    public function update(Request $request, Finish $finish): JsonResponse
    {
        $data = $request->validate([
            'name'        => "sometimes|string|max:80|unique:finishes,name,{$finish->id}",
            'description' => 'nullable|string',
            'fixed_cost'  => 'sometimes|numeric|min:0',
        ]);

        $finish->update($data);
        return response()->json($finish->fresh());
    }
    
    /**
 * @OA\Delete(
 *     path="/api/finishes/{id}",
 *     tags={"Acabados - Admin"},
 *     summary="Eliminar acabado (solo admin)",
 *     description="No se puede eliminar si hay pedidos que usan este acabado.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Acabado eliminado"),
 *     @OA\Response(response=422, description="No se puede eliminar, está en uso"),
 *     @OA\Response(response=404, description="Acabado no encontrado")
 * )
 */
    public function destroy(Finish $finish): JsonResponse
    {
        if ($finish->orderItems()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay pedidos con este acabado.'
            ], 422);
        }

        $finish->delete();
        return response()->json(['message' => 'Acabado eliminado.']);
    }
}