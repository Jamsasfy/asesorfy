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
        // Modifica la tabla existente 'procedencias'
        Schema::table('procedencias', function (Blueprint $table) {
            // Añadir 'descripcion' después de 'key'
            $table->text('descripcion')->nullable()->after('key');
            // Añadir 'activo' después de 'descripcion'
            $table->boolean('activo')->default(true)->after('descripcion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       // Revierte los cambios
       Schema::table('procedencias', function (Blueprint $table) {
        // Es buena práctica eliminar en orden inverso a la creación dentro del up()
        $table->dropColumn('activo');
        $table->dropColumn('descripcion');
    });
    }
};
