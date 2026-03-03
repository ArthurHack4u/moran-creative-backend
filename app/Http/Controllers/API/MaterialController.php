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
    public function destroy(Material $material): JsonResponse
    {
        $material->update(['active' => false]);
        return response()->json(['message' => 'Material desactivado.']);
    }

    // POST /api/materials/{id}/colors
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
    public function removeColor(MaterialColor $color): JsonResponse
    {
        $color->delete();
        return response()->json(['message' => 'Color eliminado.']);
    }
}