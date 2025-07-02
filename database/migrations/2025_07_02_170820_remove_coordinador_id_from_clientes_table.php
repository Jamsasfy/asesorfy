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
        Schema::table('clientes', function (Blueprint $table) {
        // Primero eliminamos la clave foránea si existe
        $table->dropForeign(['coordinador_id']);
        // Luego eliminamos la columna
        $table->dropColumn('coordinador_id');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         // En caso de rollback, volvemos a crear la columna
    Schema::table('clientes', function (Blueprint $table) {
        $table->foreignId('coordinador_id')->nullable()->after('asesor_id');
    });
    }
};
