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
         Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->comment('Nombre del proyecto (ej: Creación SL para Cliente X).');

            // Relación con el cliente (a quién pertenece el proyecto)
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();

            // Relación con la Venta que originó este proyecto (nullable)
            // Esto es útil para auditar de dónde viene el proyecto.
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();

            // Relación con el Servicio recurrente que este proyecto activa
            // Puede ser null si un proyecto es solo de "trabajo único" o no activa directamente una suscripción
            $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete()
                  ->comment('Servicio recurrente que este proyecto activa al finalizar.');

            // Relación con el VentaItem específico que este proyecto activa (más específico)
            // Esto es útil si tienes múltiples VentaItems de un mismo servicio.
            $table->foreignId('venta_item_id')->nullable()->constrained('venta_items')->nullOnDelete()
                  ->comment('VentaItem recurrente cuya suscripción se activa al finalizar este proyecto.');

            // Usuario asignado a este proyecto (el asesor, etc.)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('Usuario (asesor) asignado al proyecto.');

            // Estado del proyecto
            $table->string('estado', 50)->default('pendiente')->index()
                  ->comment('Estado actual del proyecto (ej: pendiente, en_progreso, finalizado, cancelado).');

            // Fechas clave
            $table->date('fecha_inicio_estimada')->nullable()->comment('Fecha estimada de inicio del proyecto.');
            $table->date('fecha_fin_estimada')->nullable()->comment('Fecha estimada de finalización del proyecto.');
            $table->dateTime('fecha_finalizacion')->nullable()->comment('Fecha y hora real de finalización del proyecto.');

            $table->text('descripcion')->nullable()->comment('Descripción detallada del proyecto.');

            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // Para borrado suave
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
