<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Pedidos
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', [
                'solicitado',
                'cotizado',
                'aceptado',
                'rechazado',
                'en_produccion',
                'listo',
                'entregado',
            ])->default('solicitado');
            $table->text('notes')->nullable();
            $table->string('end_use', 150)->nullable();
            $table->date('deadline')->nullable();
            $table->decimal('quoted_price', 10, 2)->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        // Ítems del pedido
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('material_colors')->nullOnDelete();
            $table->foreignId('finish_id')->nullable()->constrained()->nullOnDelete();
            $table->string('piece_name', 150)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('preferred_color', 80)->nullable();
            $table->text('item_notes')->nullable();
            $table->decimal('dim_x', 8, 2)->nullable();
            $table->decimal('dim_y', 8, 2)->nullable();
            $table->decimal('dim_z', 8, 2)->nullable();
            $table->unsignedTinyInteger('infill_percent')->nullable();
            $table->timestamps();
        });

        // Archivos adjuntos (STL, OBJ, etc.)
        Schema::create('order_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });

        // Historial de cambios de estado
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users');
            $table->enum('status', [
                'solicitado','cotizado','aceptado',
                'rechazado','en_produccion','listo','entregado'
            ]);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_files');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};