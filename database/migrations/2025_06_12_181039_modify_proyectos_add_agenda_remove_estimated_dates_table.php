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
        Schema::table('proyectos', function (Blueprint $table) {
            // Eliminar columnas existentes
            $table->dropColumn(['fecha_inicio_estimada', 'fecha_fin_estimada']);

            // Añadir nueva columna 'agenda'
            $table->dateTime('agenda')->nullable()->comment('Próxima fecha y hora agendada para este proyecto.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            // Revertir cambios: Eliminar 'agenda' y volver a añadir las estimadas
            $table->dropColumn('agenda');
            $table->date('fecha_inicio_estimada')->nullable()->comment('Fecha estimada de inicio del proyecto.');
            $table->date('fecha_fin_estimada')->nullable()->comment('Fecha estimada de finalización del proyecto.');
        });
    }
};
