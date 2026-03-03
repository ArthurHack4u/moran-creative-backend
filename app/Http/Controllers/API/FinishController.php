<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Finish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinishController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Finish::all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:80|unique:finishes,name',
            'description' => 'nullable|string',
            'fixed_cost'  => 'required|numeric|min:0',
        ]);

        return response()->json(Finish::create($data), 201);
    }

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