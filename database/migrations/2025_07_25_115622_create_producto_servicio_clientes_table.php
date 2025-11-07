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
         Schema::create('producto_servicio_clientes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnUpdate()->cascadeOnDelete();
        $table->string('nombre');
        $table->string('descripcion')->nullable();
        $table->foreignId('cuenta_cliente_id')->constrained('cuenta_clientes')->cascadeOnUpdate()->restrictOnDelete();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_servicio_clientes');
    }
};
