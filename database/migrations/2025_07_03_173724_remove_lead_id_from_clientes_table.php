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
        // Primero intentamos borrar la clave foránea si existe
        // Laravel genera un nombre por defecto, lo intentamos adivinar
        $table->dropForeign(['lead_id']);
    });

    Schema::table('clientes', function (Blueprint $table) {
        // Una vez borrada la clave foránea, borramos la columna
        $table->dropColumn('lead_id');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Vuelve a crear la columna si se revierte la migración
            $table->foreignId('lead_id')->nullable()->after('id')->constrained('leads')->nullOnDelete();
        });
    }
};
