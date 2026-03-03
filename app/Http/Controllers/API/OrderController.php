<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Mail\OrderStatusNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    // 1. LISTAR PEDIDOS
    /**
 * @OA\Get(
 *     path="/api/orders",
 *     tags={"Pedidos"},
 *     summary="Listar pedidos",
 *     description="Admin ve todos los pedidos. Cliente solo ve los suyos.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="status", in="query", required=false,
 *         @OA\Schema(type="string", enum={"solicitado","cotizado","aceptado","en_produccion","listo","entregado","rechazado"})
 *     ),
 *     @OA\Response(response=200, description="Lista paginada de pedidos")
 * )
 */
    public function index(Request $request): JsonResponse
    {
        $user  = auth('api')->user();
        $query = Order::with(['user:id,name,email', 'items.material', 'files'])
                      ->orderBy('created_at', 'desc');

        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate(15);
        $orders->getCollection()->transform(function ($order) {
            $order->ticket = $order->ticket;
            return $order;
        });

        return response()->json($orders);
    }

    // 2. CREAR NUEVO PEDIDO (CLIENTE)
    /**
 * @OA\Post(
 *     path="/api/orders",
 *     tags={"Pedidos"},
 *     summary="Crear nuevo pedido",
 *     description="El cliente sube su diseño STL/OBJ junto con las especificaciones.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(property="notes", type="string", example="Quiero acabado liso"),
 *                 @OA\Property(property="end_use", type="string", example="prototipo"),
 *                 @OA\Property(property="deadline", type="string", format="date", example="2026-04-01"),
 *                 @OA\Property(property="files[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="items[0][piece_name]", type="string", example="Soporte de cámara"),
 *                 @OA\Property(property="items[0][quantity]", type="integer", example=2),
 *                 @OA\Property(property="items[0][material_id]", type="integer", example=1),
 *                 @OA\Property(property="items[0][color_id]", type="integer", example=3),
 *                 @OA\Property(property="items[0][finish_id]", type="integer", example=1),
 *                 @OA\Property(property="items[0][infill_percent]", type="integer", example=20),
 *                 @OA\Property(property="items[0][dim_x]", type="number", example=50),
 *                 @OA\Property(property="items[0][dim_y]", type="number", example=30),
 *                 @OA\Property(property="items[0][dim_z]", type="number", example=20)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=201, description="Pedido creado exitosamente",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="ticket", type="string", example="MC-0042"),
 *             @OA\Property(property="order", type="object")
 *         )
 *     ),
 *     @OA\Response(response=422, description="Error de validación"),
 *     @OA\Response(response=500, description="Error interno")
 * )
 */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'notes'                  => 'nullable|string|max:1000',
            'end_use'                => 'nullable|string|max:150',
            'deadline'               => 'nullable|date|after:today',
            'items'                  => 'required|array|min:1',
            'items.*.piece_name'     => 'nullable|string|max:150',
            'items.*.quantity'       => 'required|integer|min:1',
            'items.*.material_id'    => 'nullable|exists:materials,id',
            'items.*.color_id'       => 'nullable|exists:material_colors,id',
            'items.*.finish_id'      => 'nullable|exists:finishes,id',
            'items.*.preferred_color'=> 'nullable|string|max:80',
            'items.*.item_notes'     => 'nullable|string|max:500',
            'items.*.dim_x'          => 'nullable|numeric|min:0',
            'items.*.dim_y'          => 'nullable|numeric|min:0',
            'items.*.dim_z'          => 'nullable|numeric|min:0',
            'items.*.infill_percent' => 'nullable|integer|min:10|max:100',
            'files'                  => 'nullable|array',
            'files.*'                => 'file|max:102400',
        ]);

        DB::beginTransaction();
        try {
            $user  = auth('api')->user();
            $order = Order::create([
                'user_id' => $user->id,
                'status'  => 'solicitado',
                'notes'   => $request->notes,
                'end_use' => $request->end_use,
                'deadline'=> $request->deadline,
            ]);

            foreach ($request->items as $itemData) {
                $order->items()->create($itemData);
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $storedName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs("orders/{$order->id}", $storedName, 'local');
                    $order->files()->create([
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name'   => $storedName,
                        'path'          => $path,
                        'mime_type'     => $file->getMimeType(),
                        'size_bytes'    => $file->getSize(),
                    ]);
                }
            }

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'changed_by' => $user->id,
                'status'     => 'solicitado',
                'note'       => 'Solicitud creada por el cliente.',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Solicitud enviada correctamente.',
                'ticket'  => $order->ticket,
                'order'   => $order->load(['items.material', 'files']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear la solicitud.', 'error' => $e->getMessage()], 500);
        }
    }

    // 3. MOSTRAR DETALLES
    /**
 * @OA\Get(
 *     path="/api/orders/{id}",
 *     tags={"Pedidos"},
 *     summary="Ver detalle de un pedido",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
 *     @OA\Response(response=200, description="Detalle completo del pedido con historial"),
 *     @OA\Response(response=403, description="No autorizado"),
 *     @OA\Response(response=404, description="Pedido no encontrado")
 * )
 */
    public function show(Order $order): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user->isAdmin() && $order->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $order->load([
            'user:id,name,email,phone',
            'items.material', 'items.color', 'items.finish',
            'files',
            'statusHistory.changedBy:id,name,role',
        ]);

        $order->ticket = $order->ticket;
        return response()->json($order);
    }

    // 4. ESTABLECER COTIZACIÓN (ADMIN)
    /**
 * @OA\Patch(
 *     path="/api/orders/{id}/quote",
 *     tags={"Pedidos - Admin"},
 *     summary="Enviar cotización al cliente",
 *     description="Solo el administrador puede cotizar. El pedido debe estar en estado 'solicitado'.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"quoted_price"},
 *             @OA\Property(property="quoted_price", type="number", format="float", example=850.00),
 *             @OA\Property(property="admin_notes", type="string", example="Incluye lijado y envío en 5 días hábiles.")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Cotización enviada",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(property="order", type="object")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Solo el admin puede cotizar"),
 *     @OA\Response(response=422, description="Estado inválido o precio requerido")
 * )
 */
    public function quote(Request $request, Order $order): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Solo el administrador puede cotizar.'], 403);
        }

        if ($order->status !== 'solicitado') {
            return response()->json(['message' => 'Solo se pueden cotizar pedidos en estado solicitado.'], 422);
        }

        $request->validate([
            'quoted_price' => 'required|numeric|min:0',
            'admin_notes'  => 'nullable|string|max:1000',
        ]);

        $order->update([
            'status'       => 'cotizado',
            'quoted_price' => $request->quoted_price,
            'admin_notes'  => $request->admin_notes,
            'quoted_at'    => now(),
        ]);

        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'changed_by' => $user->id,
            'status'     => 'cotizado',
            'note'       => 'Cotización establecida en $' . number_format($request->quoted_price, 2) . ' MXN.',
        ]);

        return response()->json([
            'message' => 'Cotización enviada al cliente.',
            'order'   => $order->fresh(),
        ]);
    }

    // 5. RESPONDER A COTIZACIÓN (CLIENTE)
    /**
 * @OA\Patch(
 *     path="/api/orders/{id}/respond",
 *     tags={"Pedidos"},
 *     summary="Cliente acepta o rechaza la cotización",
 *     description="Solo el dueño del pedido puede responder. El pedido debe estar en estado 'cotizado'.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"action"},
 *             @OA\Property(property="action", type="string", enum={"accept","reject"}, example="accept"),
 *             @OA\Property(property="note", type="string", example="El precio me parece alto.")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Respuesta registrada"),
 *     @OA\Response(response=403, description="No autorizado o estado inválido")
 * )
 */
    public function respond(Request $request, Order $order): JsonResponse
    {
        $user = auth('api')->user();

        if (!$order->canBeRespondedBy($user)) {
            return response()->json(['message' => 'No puedes responder a esta solicitud.'], 403);
        }

        $request->validate([
            'action' => 'required|in:accept,reject',
            'note'   => 'nullable|string|max:500',
        ]);

        $newStatus = $request->action === 'accept' ? 'aceptado' : 'rechazado';
        $note      = $request->action === 'accept'
            ? 'Cliente aceptó la cotización.'
            : 'Cliente rechazó la cotización. ' . ($request->note ?? '');

        $order->update(['status' => $newStatus, 'responded_at' => now()]);

        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'changed_by' => $user->id,
            'status'     => $newStatus,
            'note'       => $note,
        ]);

        return response()->json([
            'message' => $request->action === 'accept'
                ? '¡Cotización aceptada! Pronto iniciaremos la producción.'
                : 'Cotización rechazada.',
            'order' => $order->fresh(),
        ]);
    }

    // 6. ACTUALIZAR ESTADO (ADMIN)
    /**
 * @OA\Patch(
 *     path="/api/orders/{id}/status",
 *     tags={"Pedidos - Admin"},
 *     summary="Actualizar estado de producción",
 *     description="Solo admin. Transiciones válidas: aceptado→en_produccion→listo→entregado. Envía correo automático al cliente.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"status"},
 *             @OA\Property(property="status", type="string", enum={"en_produccion","listo","entregado"}),
 *             @OA\Property(property="note", type="string", example="Iniciando impresión")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Estado actualizado y correo enviado"),
 *     @OA\Response(response=422, description="Transición de estado inválida"),
 *     @OA\Response(response=403, description="Solo el admin puede hacer esto")
 * )
 */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Solo el administrador puede cambiar el estado.'], 403);
        }

        $request->validate([
            'status' => ['required', Rule::in(['en_produccion', 'listo', 'entregado'])],
            'note'   => 'nullable|string|max:500',
        ]);

        $validTransitions = [
            'aceptado'      => ['en_produccion'],
            'en_produccion' => ['listo'],
            'listo'         => ['entregado'],
        ];

        if (!isset($validTransitions[$order->status]) ||
            !in_array($request->status, $validTransitions[$order->status])) {
            return response()->json([
                'message' => "No se puede cambiar de '{$order->status}' a '{$request->status}'.",
            ], 422);
        }

        $order->update(['status' => $request->status]);

        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'changed_by' => $user->id,
            'status'     => $request->status,
            'note'       => $request->note,
        ]);

        // ENVIAR CORREO AUTOMÁTICO AL CAMBIAR ESTADO
        try {
            $labels = [
                'en_produccion' => 'En Producción',
                'listo'         => 'Listo para Entrega',
                'entregado'     => 'Entregado'
            ];
            Mail::to($order->user->email)->send(new OrderStatusNotification($order, $labels[$request->status]));
        } catch (\Exception $e) {
            \Log::error("Error enviando correo: " . $e->getMessage());
        }

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'order'   => $order->fresh(),
        ]);
    }

    // 7. DESCARGAR ARCHIVO SEGURO
    /**
 * @OA\Get(
 *     path="/api/orders/files/{fileId}/download",
 *     tags={"Pedidos"},
 *     summary="Descargar archivo STL/OBJ de un pedido",
 *     description="Admin descarga cualquier archivo. Cliente solo descarga archivos de sus propios pedidos.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="fileId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Descarga del archivo"),
 *     @OA\Response(response=403, description="No autorizado"),
 *     @OA\Response(response=404, description="Archivo no encontrado")
 * )
 */
    public function downloadFile($fileId)
    {
        $user = auth('api')->user();
        
        $fileRecord = \App\Models\OrderFile::findOrFail($fileId);
        $order = \App\Models\Order::findOrFail($fileRecord->order_id);

        if (!$user->isAdmin() && $order->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if (!Storage::disk('local')->exists($fileRecord->path)) {
            return response()->json(['message' => 'Archivo no encontrado en el servidor'], 404);
        }

        return Storage::disk('local')->download($fileRecord->path, $fileRecord->original_name);
    }

    // 8. SUBIR COMPROBANTE DE PAGO (CLIENTE)
    /**
 * @OA\Post(
 *     path="/api/orders/{id}/payment-proof",
 *     tags={"Pedidos"},
 *     summary="Subir comprobante de pago",
 *     description="El cliente sube una imagen de su comprobante. El admin la valida manualmente.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"proof"},
 *                 @OA\Property(property="proof", type="string", format="binary",
 *                     description="Imagen del comprobante (JPG/PNG, máx 5MB)")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Comprobante subido exitosamente"),
 *     @OA\Response(response=400, description="No se recibió archivo"),
 *     @OA\Response(response=403, description="No autorizado"),
 *     @OA\Response(response=422, description="Archivo inválido")
 * )
 */
    public function uploadPaymentProof(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'proof' => 'required|image|max:5120', // Máximo 5MB
        ]);

        $user = auth('api')->user();

        // Seguridad: Solo el dueño del pedido puede subir su propio pago
        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($request->hasFile('proof')) {
            $file = $request->file('proof');
            $storedName = 'proof_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // Guardamos en una subcarpeta específica para tener todo ordenado
            $path = $file->storeAs("orders/{$order->id}/proofs", $storedName, 'local');

            // Creamos el registro del archivo para que aparezca en el panel de admin
            $order->files()->create([
                'original_name' => 'Comprobante_Pago_' . $order->ticket,
                'stored_name'   => $storedName,
                'path'          => $path,
                'mime_type'     => $file->getMimeType(),
                'size_bytes'    => $file->getSize(),
            ]);

            return response()->json([
                'message' => 'Comprobante subido con éxito. El taller validará tu pago pronto.'
            ]);
        }

        return response()->json(['message' => 'No se recibió ningún archivo.'], 400);
    }
}