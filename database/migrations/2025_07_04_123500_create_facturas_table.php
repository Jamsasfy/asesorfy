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
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('venta_id')->nullable()->constrained('ventas');

            $table->string('serie');
            $table->string('numero_factura')->unique();
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');

            $table->enum('estado', ['borrador', 'pendiente_pago', 'pagada', 'vencida', 'anulada'])->default('borrador');
            $table->enum('metodo_pago', ['transferencia', 'domiciliacion', 'stripe', 'otro'])->nullable();

            // IDs para la integración con Stripe
            $table->string('stripe_invoice_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();

            $table->decimal('base_imponible', 10, 2);
            $table->decimal('total_iva', 10, 2);
            $table->decimal('total_factura', 10, 2);

            $table->text('observaciones_publicas')->nullable();
            $table->text('observaciones_privadas')->nullable();

            // Para facturas rectificativas
            $table->unsignedBigInteger('factura_rectificada_id')->nullable();
            $table->text('motivo_rectificacion')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
