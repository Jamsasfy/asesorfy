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
            // 1. Añadimos las nuevas columnas polimórficas
            $table->morphs('facturable'); // <-- Esto crea las columnas `facturable_id` y `facturable_type`

            // 2. Eliminamos las columnas antiguas del snapshot de 'Tercero'
            $table->dropColumn(['tercero_nombre', 'tercero_nif', 'tercero_direccion']);
        });
    }

    public function down(): void
    {
        Schema::table('registro_facturas', function (Blueprint $table) {
            // Revierte los cambios: primero elimina las nuevas columnas
            $table->dropMorphs('facturable');

            // Y después vuelve a añadir las antiguas
            $table->string('tercero_nombre');
            $table->string('tercero_nif');
            $table->text('tercero_direccion')->nullable();
        });
    }
};
