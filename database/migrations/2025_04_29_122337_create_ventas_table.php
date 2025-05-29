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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            // FK al cliente (obligatorio, una venta siempre tiene un cliente)
            $table->foreignId('cliente_id')
                  ->constrained('clientes')
                  ->onUpdate('cascade')
                  ->cascadeOnDelete(); // Si se elimina el cliente, ¿se eliminan las ventas? O nullOnDelete()? Cascade es común si la venta no tiene sentido sin el cliente.

            // FK al lead de origen (nullable, no todas las ventas vienen de un lead rastreado)
            $table->foreignId('lead_id')
                  ->nullable()
                  ->constrained('leads')
                  ->onUpdate('cascade')
                  ->nullOnDelete();

            // FK al usuario (comercial) que cerró la venta
            $table->foreignId('user_id')
                  ->nullable() // O required() si siempre se asigna un comercial
                  ->constrained('users')
                  ->onUpdate('cascade')
                  ->nullOnDelete();

            $table->dateTime('fecha_venta'); // Fecha en que se cerró la venta
            $table->decimal('importe_total', 10, 2)->default(0); // Total calculado de los items           
            $table->text('observaciones')->nullable(); // Observaciones generales de la venta

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
