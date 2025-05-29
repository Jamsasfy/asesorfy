<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('venta_items', function (Blueprint $table) {
            $table->id();
            // FK a la venta a la que pertenece este item (obligatorio)
            $table->foreignId('venta_id')
                  ->constrained('ventas')
                  ->onUpdate('cascade')
                  ->cascadeOnDelete(); // Si se elimina la venta, sus items deben eliminarse.

            // FK al servicio que se vendió (obligatorio)
            $table->foreignId('servicio_id')
                  ->constrained('servicios')
                  ->onUpdate('cascade')
                  ->restrictOnDelete(); // Restrict es seguro para no borrar ventas si eliminas un servicio.

            $table->integer('cantidad')->default(1); // Cantidad del servicio
            $table->decimal('precio_unitario', 10, 2); // Precio del servicio EN EL MOMENTO DE LA VENTA
            $table->decimal('subtotal', 10, 2); // Cantidad * Precio Unitario (se calcula)

            $table->text('observaciones_item')->nullable(); // Notas específicas de este item
            // Opcional: Fecha de inicio si es un servicio recurrente
            $table->date('fecha_inicio_servicio')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_items');
    }
};
