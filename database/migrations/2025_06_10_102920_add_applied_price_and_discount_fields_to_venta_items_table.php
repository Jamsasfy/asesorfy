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
        Schema::table('venta_items', function (Blueprint $table) {
            // El subtotal original (cantidad * precio_unitario) sin descuento
            // Si tu 'subtotal' actual ya es así, no necesitas cambiarlo, pero asegúrate que no se recalcula en el modelo.
            // Si el subtotal de tu tabla actual es el que incluye descuento, cambia su nombre.
            // Para consistencia, asumimos que 'subtotal' es el BASE sin descuento.
            // Si no existe, lo añades.
            if (!Schema::hasColumn('venta_items', 'subtotal')) {
                 $table->decimal('subtotal', 10, 2)->after('precio_unitario')->comment('Subtotal de la línea (cantidad * precio_unitario), sin descuentos ni IVA.');
            }

            // Nuevo campo: precio unitario con descuento aplicado, SIN IVA
            $table->decimal('precio_unitario_aplicado', 10, 4)->nullable()->after('subtotal')
                  ->comment('Precio unitario del servicio aplicado en esta venta, con el descuento de línea, sin IVA.');

            // Nuevo campo: subtotal final de la línea CON descuento, SIN IVA
            $table->decimal('subtotal_aplicado', 10, 2)->nullable()->after('precio_unitario_aplicado')
                  ->comment('Subtotal final de la línea (cantidad * precio_unitario_aplicado), sin IVA.');

            // Campos para el tipo y valor del descuento
            $table->string('descuento_tipo', 50)->nullable()->after('subtotal_aplicado')
                  ->comment('Tipo de descuento aplicado (porcentaje, fijo, precio_final).');
            $table->decimal('descuento_valor', 10, 2)->nullable()->after('descuento_tipo')
                  ->comment('Valor numérico del descuento (ej: 50 para 50% o 10.00 para 10€ fijo).');

            // Campos para la duración y validez del descuento temporal
            $table->unsignedInteger('descuento_duracion_meses')->nullable()->after('descuento_valor')
                  ->comment('Duración del descuento en meses (si aplica).');
            $table->date('descuento_valido_hasta')->nullable()->after('descuento_duracion_meses')
                  ->comment('Fecha hasta la que el descuento es válido (calculado).');

            // Campo para observaciones/descripción del descuento
            $table->text('observaciones_descuento')->nullable()->after('descuento_valido_hasta')
                  ->comment('Notas o descripción específica del descuento aplicado a este ítem.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('venta_items', function (Blueprint $table) {
            // Solo eliminar las columnas que se añadieron en esta migración.
            $table->dropColumn([
                'precio_unitario_aplicado',
                'subtotal_aplicado', // Nuevo campo
                'descuento_tipo',
                'descuento_valor',
                'descuento_duracion_meses',
                'descuento_valido_hasta',
                'observaciones_descuento',
            ]);
            // Si subtotal fue añadido aquí, también debería ser eliminado.
            // if (Schema::hasColumn('venta_items', 'subtotal')) {
            //      $table->dropColumn('subtotal');
            // }
        });
    }
};
