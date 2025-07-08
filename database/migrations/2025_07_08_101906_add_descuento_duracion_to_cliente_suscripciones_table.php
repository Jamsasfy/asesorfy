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
        Schema::table('cliente_suscripciones', function (Blueprint $table) {
        $table->integer('descuento_duracion_meses')->nullable()->after('descuento_valor');
         });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cliente_suscripciones', function (Blueprint $table) {
            $table->dropColumn('descuento_duracion_meses');
        });
    }
};
