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
        // Añadimos el campo para guardar la ruta del fichero, justo como en tu modelo Documento
        $table->string('justificante_path')->nullable()->after('motivo_rechazo');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registro_facturas', function (Blueprint $table) {
        $table->dropColumn('justificante_path');
    });
    }
};
