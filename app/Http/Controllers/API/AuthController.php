<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // POST /api/auth/register
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'      => 'required|string|min:2|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'phone'     => 'nullable|string|max:20',
            'address'   => 'nullable|string|max:500',
        ]);

        $user  = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'client',
            'phone'    => $request->phone,
            'address'  => $request->address,
        ]);

        $token = auth('api')->login($user);

        return response()->json([
            'message' => 'Registro exitoso.',
            'user'    => $this->userData($user),
            'token'   => $token,
        ], 201);
    }

    // POST /api/auth/login
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        return response()->json([
            'message' => 'Sesión iniciada.',
            'user'    => $this->userData(auth('api')->user()),
            'token'   => $token,
        ]);
    }

    // POST /api/auth/logout
    public function logout(): JsonResponse
    {
        auth('api')->logout();
        return response()->json(['message' => 'Sesión cerrada.']);
    }

    // GET /api/auth/me
    public function me(): JsonResponse
    {
        return response()->json($this->userData(auth('api')->user()));
    }

    // POST /api/auth/refresh
    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();
        return response()->json([
            'token' => $token,
            'user'  => $this->userData(auth('api')->user()),
        ]);
    }

    private function userData(User $user): array
    {
        return [
            'id'      => $user->id,
            'name'    => $user->name,
            'email'   => $user->email,
            'role'    => $user->role,
            'phone'   => $user->phone,
            'address' => $user->address,
        ];
    }
}