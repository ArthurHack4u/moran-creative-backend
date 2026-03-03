<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage; 
use Illuminate\Support\Str;           

/**
 * @OA\Info(
 * title="PrintFlow API - Moran Creative",
 * version="1.0.0",
 * description="API para gestión de pedidos de impresión 3D",
 * @OA\Contact(email="admin@morancreative.com")
 * )
 *
 * @OA\Server(
 * url="http://127.0.0.1:8000",
 * description="Servidor local"
 * )
 *
 * @OA\SecurityScheme(
 * securityScheme="bearerAuth",
 * type="http",
 * scheme="bearer",
 * bearerFormat="JWT"
 * )
 */
class AuthController extends Controller
{
    // POST /api/auth/register
    /**
     * @OA\Post(
     * path="/api/auth/register",
     * tags={"Autenticación"},
     * summary="Registrar nuevo cliente",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"name","email","password","password_confirmation"},
     * @OA\Property(property="name", type="string", example="Juan Pérez"),
     * @OA\Property(property="email", type="string", example="juan@ejemplo.com"),
     * @OA\Property(property="password", type="string", example="MiPassword123!"),
     * @OA\Property(property="password_confirmation", type="string"),
     * @OA\Property(property="phone", type="string", example="981 234 5678"),
     * @OA\Property(property="address", type="string")
     * )
     * ),
     * @OA\Response(response=201, description="Registro exitoso"),
     * @OA\Response(response=422, description="Error de validación")
     * )
     */
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
    /**
     * @OA\Post(
     * path="/api/auth/login",
     * tags={"Autenticación"},
     * summary="Iniciar sesión",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"email","password"},
     * @OA\Property(property="email", type="string", example="admin@morancreative.com"),
     * @OA\Property(property="password", type="string", example="Admin1234!")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Login exitoso",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string"),
     * @OA\Property(property="token", type="string"),
     * @OA\Property(property="user", type="object")
     * )
     * ),
     * @OA\Response(response=401, description="Credenciales incorrectas")
     * )
     */
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
    /**
     * @OA\Post(
     * path="/api/auth/logout",
     * tags={"Autenticación"},
     * summary="Cerrar sesión",
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=200, description="Sesión cerrada")
     * )
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout();
        return response()->json(['message' => 'Sesión cerrada.']);
    }

    // GET /api/auth/me
    /**
     * @OA\Get(
     * path="/api/auth/me",
     * tags={"Autenticación"},
     * summary="Obtener usuario autenticado",
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=200, description="Datos del usuario")
     * )
     */
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

    // POST /api/auth/avatar
    /**
     * @OA\Post(
     * path="/api/auth/avatar",
     * tags={"Autenticación"},
     * summary="Actualizar foto de perfil",
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * @OA\Property(property="avatar_file", type="string", format="binary", description="Archivo de imagen (máx 5MB)"),
     * @OA\Property(property="avatar_url", type="string", description="URL externa de la imagen")
     * )
     * )
     * ),
     * @OA\Response(response=200, description="Foto actualizada con éxito"),
     * @OA\Response(response=400, description="No se recibió imagen")
     * )
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar_file' => 'nullable|image|max:5120',
            'avatar_url'  => 'nullable|url|max:500',
        ]);

        $user = auth('api')->user();

        if ($request->hasFile('avatar_file')) {
            // Borramos la imagen anterior para no llenar el disco del servidor
            if ($user->avatar && Str::startsWith($user->avatar, '/storage/avatars/')) {
                $oldPath = str_replace('/storage/', '', $user->avatar);
                Storage::disk('public')->delete($oldPath);
            }

            $file = $request->file('avatar_file');
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Guardamos en storage/app/public/avatars
            $path = $file->storeAs('avatars', $filename, 'public');
            $user->avatar = '/storage/' . $path;
            
        } elseif ($request->filled('avatar_url')) {
            $user->avatar = $request->avatar_url;
        } else {
            return response()->json(['message' => 'No se recibió ninguna imagen.'], 400);
        }

        $user->save();

        return response()->json([
            'message' => 'Foto de perfil actualizada con éxito.',
            'user'    => $this->userData($user) // Devolvemos el usuario completo actualizado
        ]);
    }

    // Helper: Centraliza los datos del usuario para mantener consistencia
    private function userData(User $user): array
    {
        return [
            'id'      => $user->id,
            'name'    => $user->name,
            'email'   => $user->email,
            'role'    => $user->role,
            'phone'   => $user->phone,
            'address' => $user->address,
            'avatar'  => $user->avatar,
        ];
    }
}