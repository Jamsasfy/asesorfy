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
        Schema::create('cliente_suscripciones', function (Blueprint $table) {
            $table->id();

            // --- Claves Foráneas Principales ---
            // Relación con el Cliente
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            // Relación con el Servicio contratado
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            // Relación con la Venta que originó esta suscripción (opcional)
            $table->foreignId('venta_origen_id')->nullable()->constrained('ventas')->nullOnDelete();

            // --- Detalles del Servicio en esta Suscripción ---
            $table->boolean('es_tarifa_principal')->default(false)->comment('Indica si es la tarifa base del cliente en este periodo.');
            $table->decimal('precio_acordado', 10, 2);
            $table->unsignedInteger('cantidad')->default(1);

            // --- Ciclo de Vida de la Suscripción ---
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 30)->default('pendiente_activacion')->index()->comment('Ej: activa, cancelada, finalizada, etc.');

            // --- Detalles del Descuento (todos opcionales) ---
            $table->string('descuento_tipo')->nullable()->comment('Ej: porcentaje, fijo, precio_final');
            $table->decimal('descuento_valor', 10, 2)->nullable();
            $table->string('descuento_descripcion')->nullable();
            $table->date('descuento_valido_hasta')->nullable();

            // --- Campos Adicionales y de Gestión ---
            $table->text('observaciones')->nullable();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('ciclo_facturacion', 20)->nullable()->comment('Ej: mensual, anual');
            $table->date('proxima_fecha_facturacion')->nullable()->index();
            $table->json('datos_adicionales')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_suscripciones');
    }
};
