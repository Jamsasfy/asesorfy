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
    Schema::create('proveedores', function (Blueprint $table) {
        $table->id();

        // Cada proveedor pertenece a un Cliente de AsesorFy
        $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();

        $table->string('nombre');
        $table->string('nif')->unique();
        $table->string('direccion')->nullable();
        $table->string('codigo_postal')->nullable();
        $table->string('ciudad')->nullable();
        $table->string('provincia')->nullable();
        $table->string('pais')->nullable();
        $table->string('email')->nullable();
        $table->string('telefono')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proveedors');
    }
};
