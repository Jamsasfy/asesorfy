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
        Schema::table('registro_factura_lineas', function (Blueprint $table) {
            if (Schema::hasColumn('registro_factura_lineas', 'descuento_tipo')) {
                $table->dropColumn('descuento_tipo');
            }

            $table->string('cuenta_contable_codigo')->nullable()->after('descuento_valor');
            $table->string('cuenta_contable_descripcion')->nullable()->after('cuenta_contable_codigo');

            $table->decimal('importe', 15, 2)->after('subtotal');
            $table->decimal('iva', 15, 2)->after('importe');

            $table->decimal('porcentaje_retencion_irpf', 5, 2)->nullable()->after('iva');

            $table->string('tipo_operacion')->nullable()->after('porcentaje_retencion_irpf');

            $table->string('producto_servicio_id')->nullable()->after('tipo_operacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('registro_factura_lineas', function (Blueprint $table) {
            $table->string('descuento_tipo')->nullable()->after('descuento_valor');

            $table->dropColumn([
                'cuenta_contable_codigo',
                'cuenta_contable_descripcion',
                'importe',
                'iva',
                'porcentaje_retencion_irpf',
                'tipo_operacion',
                'producto_servicio_id',
            ]);
        });
    }
};
