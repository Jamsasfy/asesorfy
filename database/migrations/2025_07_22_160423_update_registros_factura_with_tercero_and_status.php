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
        Schema::table('registro_facturas', function (Blueprint $table) {
        // 1. Añadimos la relación con el Tercero y los campos para el "snapshot"
     

       
        $table->text('tercero_direccion')->nullable()->after('tercero_nif');

        // 2. Añadimos los campos de estado
        $table->string('estado')->default('pendiente')->after('tercero_direccion');
        $table->text('motivo_rechazo')->nullable()->after('estado');

        // 3. Eliminamos los campos originales que ahora son redundantes
        // (Si los creaste en la primera migración)
        // En nuestro caso ya no hace falta porque estamos creando la migración buena desde cero.
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registro_facturas', function (Blueprint $table) {
        // Primero se elimina la clave foránea
        $table->dropForeign(['tercero_id']);

        // Después se eliminan todas las columnas que añadimos
        $table->dropColumn([
            'tercero_id',
            'tercero_nombre',
            'tercero_nif',
            'tercero_direccion',
            'estado',
            'motivo_rechazo',
        ]);
    });
    }
};
