<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Mail\OrderStatusNotification; // Importante para los avisos por correo
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail; // Para disparar los correos
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    // 1. LISTAR PEDIDOS
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