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
            // Guardará el importe del descuento que se aplicó a esta línea
            $table->decimal('importe_descuento', 10, 2)->default(0)->after('precio_unitario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factura_items', function (Blueprint $table) {
            $table->dropColumn('importe_descuento');
        });
    }
};
