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
         Schema::create('registro_facturas', function (Blueprint $table) {
        $table->id();

        // Relación con el cliente de AsesorFy
        $table->foreignId('cliente_id')->constrained()->cascadeOnDelete();

        // Columna para saber si es emitida o recibida
        $table->string('tipo'); // ej: 'emitida' o 'recibida'

        // Campos unificados para el tercero (receptor o emisor)
        $table->string('tercero_nif')->nullable();
        $table->string('tercero_nombre')->nullable();

        // Campos comunes
        $table->date('fecha_expedicion');
        $table->date('fecha_operacion')->nullable();
        $table->string('numero_factura');

        // Campos económicos
        $table->decimal('base_imponible', 10, 2)->default(0);
        $table->decimal('cuota_iva', 10, 2)->default(0);
        $table->decimal('total_iva', 10, 2)->default(0);
        $table->decimal('tipo_retencion', 5, 2)->default(0);
        $table->decimal('retencion_irpf', 10, 2)->default(0);
        $table->decimal('total_factura', 10, 2)->default(0);

        // Medio de pago/cobro unificado
        $table->string('medio')->nullable();

        // Campos que se automatizarán desde el modelo
        $table->string('trimestre', 2)->nullable();
        $table->year('ejercicio')->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registro_facturas');
    }
};
