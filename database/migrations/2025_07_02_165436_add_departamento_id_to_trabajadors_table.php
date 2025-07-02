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
        Schema::table('trabajadors', function (Blueprint $table) {
            // Añade la columna para el departamento
            $table->foreignId('departamento_id')->nullable()->after('oficina_id')->constrained('departamentos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trabajadors', function (Blueprint $table) {
            // Elimina la clave foránea y la columna
            $table->dropForeign(['departamento_id']);
            $table->dropColumn('departamento_id');
        });
    }
};
