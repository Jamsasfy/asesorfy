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
          
            $table->decimal('subtotal_con_iva', 10, 2)->nullable()->after('subtotal')
                  ->comment('Subtotal de la línea (base * cantidad), con IVA, sin descuento.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
                       $table->dropColumn('subtotal_con_iva');
        });
    }
};
