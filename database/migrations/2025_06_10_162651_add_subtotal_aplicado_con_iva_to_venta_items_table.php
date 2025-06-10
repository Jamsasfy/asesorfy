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
            // Este campo guarda el subtotal de la línea CON descuento, y CON IVA.
            // Se coloca después de 'subtotal_aplicado' para mantener orden lógico.
            $table->decimal('subtotal_aplicado_con_iva', 10, 2)->nullable()->after('subtotal_aplicado')
                  ->comment('Subtotal descuento final del item (cantidad * precio_unitario_aplicado), con IVA.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
                        $table->dropColumn('subtotal_aplicado_con_iva');

        });
    }
};
