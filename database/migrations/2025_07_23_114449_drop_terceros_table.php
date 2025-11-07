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
       // Primero, eliminamos la columna que apunta a 'terceros'
        Schema::table('registro_facturas', function (Blueprint $table) {
            // Esta línea elimina la restricción de clave foránea
            $table->dropForeign(['tercero_id']);
            // Esta línea elimina la columna 'tercero_id'
            $table->dropColumn('tercero_id');
        });

        // Ahora sí, podemos eliminar la tabla 'terceros' sin problemas
        Schema::dropIfExists('terceros');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // El método 'down' revierte los cambios en orden inverso

        // 1. Vuelve a crear la tabla 'terceros'
        Schema::create('terceros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('nif')->unique();
            $table->timestamps();
            // ...Añade aquí el resto de campos que tenías...
        });

        // 2. Vuelve a añadir la columna 'tercero_id' a 'registro_facturas'
        Schema::table('registro_facturas', function (Blueprint $table) {
            $table->foreignId('tercero_id')->nullable()->constrained('terceros')->nullOnDelete();
        });
    }
};
