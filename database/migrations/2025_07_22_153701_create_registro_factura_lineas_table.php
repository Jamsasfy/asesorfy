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
       Schema::create('registro_factura_lineas', function (Blueprint $table) {
        $table->id();

        // Relación con la tabla principal 'registro_facturas'
        $table->foreignId('registro_factura_id')->constrained('registro_facturas')->cascadeOnDelete();

        // Relación opcional con un catálogo de servicios predefinidos
        // // // // // // ver porque ya existe / // // // /// /
        $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();

        $table->text('descripcion');
        $table->decimal('cantidad', 10, 2)->default(1);
        $table->decimal('precio_unitario', 10, 2)->default(0);
        $table->decimal('porcentaje_iva', 5, 2)->default(21.00);

        // Tipo de descuento ('porcentaje' o 'fijo')
        $table->string('descuento_tipo')->nullable(); 
        $table->decimal('descuento_valor', 10, 2)->nullable();

        $table->decimal('subtotal', 15, 2); // Calculado: (cantidad * precio_unitario) - descuento

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registro_factura_lineas');
    }
};
