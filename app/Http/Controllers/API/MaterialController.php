<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialColor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    // GET /api/materials
    /**
 * @OA\Get(
 *     path="/api/materials",
 *     tags={"Materiales"},
 *     summary="Listar materiales con colores (público)",
 *     @OA\Response(
 *         response=200,
 *         description="Lista de materiales activos con sus colores disponibles"
 *     )
 * )
 */
    public function index(): JsonResponse
    {
        return response()->json(Material::active()->with('colors')->get());
    }

    // GET /api/materials/{id}
    public function show(Material $material): JsonResponse
    {
        return response()->json($material->load('colors'));
    }

    // POST /api/materials
    /**
 * @OA\Post(
 *     path="/api/materials",
 *     tags={"Materiales - Admin"},
 *     summary="Crear material (solo admin)",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name","density_g_cm3","price_per_gram"},
 *             @OA\Property(property="name", type="string", example="PLA"),
 *             @OA\Property(property="density_g_cm3", type="number", example=1.24),
 *             @OA\Property(property="price_per_gram", type="number", example=2.00),
 *             @OA\Property(property="active", type="boolean", example=true)
 *         )
 *     ),
 *     @OA\Response(response=201, description="Material creado"),
 *     @OA\Response(response=422, description="Error de validación")
 * )
 */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100|unique:materials,name',
            'density_g_cm3'  => 'required|numeric|min:0.1',
            'price_per_gram' => 'required|numeric|min:0',
        ]);

        return response()->json(Material::create($data), 201);
    }

    // PUT /api/materials/{id}
    /**
 * @OA\Put(
 *     path="/api/materials/{id}",
 *     tags={"Materiales - Admin"},
 *     summary="Actualizar material (solo admin)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", example="PLA+"),
 *             @OA\Property(property="density_g_cm3", type="number", example=1.24),
 *             @OA\Property(property="price_per_gram", type="number", example=2.50),
 *             @OA\Property(property="active", type="boolean", example=true)
 *         )
 *     ),
 *     @OA\Response(response=200, description="Material actualizado"),
 *     @OA\Response(response=404, description="Material no encontrado")
 * )
 */
    public function update(Request $request, Material $material): JsonResponse
    {
        $data = $request->validate([
            'name'           => "sometimes|string|max:100|unique:materials,name,{$material->id}",
            'density_g_cm3'  => 'sometimes|numeric|min:0.1',
            'price_per_gram' => 'sometimes|numeric|min:0',
            'active'         => 'sometimes|boolean',
        ]);

        $material->update($data);
        return response()->json($material->fresh('colors'));
    }

    // DELETE /api/materials/{id}
    /**
 * @OA\Delete(
 *     path="/api/materials/{id}",
 *     tags={"Materiales - Admin"},
 *     summary="Eliminar material (solo admin)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Material eliminado"),
 *     @OA\Response(response=404, description="Material no encontrado")
 * )
 */
    public function destroy(Material $material): JsonResponse
    {
        $material->update(['active' => false]);
        return response()->json(['message' => 'Material desactivado.']);
    }

    // POST /api/materials/{id}/colors
    /**
 * @OA\Post(
 *     path="/api/materials/{id}/colors",
 *     tags={"Materiales - Admin"},
 *     summary="Agregar color a un material (solo admin)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"color_name","hex_code"},
 *             @OA\Property(property="color_name", type="string", example="Rojo"),
 *             @OA\Property(property="hex_code", type="string", example="#DC2626"),
 *             @OA\Property(property="extra_cost", type="number", example=0)
 *         )
 *     ),
 *     @OA\Response(response=201, description="Color agregado")
 * )
 */
    public function addColor(Request $request, Material $material): JsonResponse
    {
        $data = $request->validate([
            'color_name' => 'required|string|max:80',
            'hex_code'   => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'extra_cost' => 'nullable|numeric|min:0',
        ]);

        return response()->json($material->colors()->create($data), 201);
    }

    // DELETE /api/material-colors/{id}
    /**
 * @OA\Delete(
 *     path="/api/materials/{id}/colors/{colorId}",
 *     tags={"Materiales - Admin"},
 *     summary="Eliminar color de un material (solo admin)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="colorId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Color eliminado"),
 *     @OA\Response(response=404, description="Color no encontrado")
 * )
 */
    public function removeColor(MaterialColor $color): JsonResponse
    {
        $color->delete();
        return response()->json(['message' => 'Color eliminado.']);
    }
}