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
        Schema::table('factura_items', function (Blueprint $table) {
            // Columna para vincular la línea de factura a la suscripción original
            $table->foreignId('cliente_suscripcion_id')
                  ->nullable() // Puede ser nulo si la línea no viene de una suscripción
                  ->constrained('cliente_suscripciones')
                  ->after('factura_id'); // Colócala después de 'factura_id' o donde prefieras

            // Columna para el precio unitario final aplicado después de descuentos
            $table->decimal('precio_unitario_aplicado', 10, 2)
                  ->nullable() // Puede ser nulo si no hay descuentos o si el precio_unitario es el mismo
                  ->after('precio_unitario'); // Colócala después de 'precio_unitario'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factura_items', function (Blueprint $table) {
            $table->dropForeign(['cliente_suscripcion_id']);
            $table->dropColumn('cliente_suscripcion_id');
            $table->dropColumn('precio_unitario_aplicado');
        });
    }
};
