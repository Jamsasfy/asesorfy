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
        Schema::table('cuenta_clientes', function (Blueprint $table) {
            // 1. Elimina la clave única que solo afecta a 'codigo'
            // El nombre 'cuenta_clientes_codigo_unique' puede variar si lo cambiaste.
            $table->dropUnique('cuenta_clientes_codigo_unique');
            
            // 2. Crea una nueva clave única compuesta por 'codigo' Y 'cliente_id'
            $table->unique(['codigo', 'cliente_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuenta_clientes', function (Blueprint $table) {
            // Esto revierte los cambios si haces un rollback
            $table->dropUnique(['codigo', 'cliente_id']);
            $table->unique('codigo', 'cuenta_clientes_codigo_unique');
        });
    }
};
