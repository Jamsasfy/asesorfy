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
           $table->foreignId('cliente_suscripcion_id')
      ->nullable()
      ->constrained('cliente_suscripciones')
      ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->dropForeign(['cliente_suscripcion_id']);
            $table->dropColumn('cliente_suscripcion_id');
        });
    }
};
