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
        $table->string('descuento_tipo')->nullable()->after('importe_descuento');
        $table->decimal('descuento_valor', 10, 2)->nullable()->after('descuento_tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('factura_items', function (Blueprint $table) {
        $table->dropColumn(['descuento_tipo', 'descuento_valor']);
    });
    }
};
