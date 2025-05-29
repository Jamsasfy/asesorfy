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
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['servicio_propuesto_id']);
            $table->dropColumn('servicio_propuesto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('servicio_propuesto_id')
                  ->nullable()
                  ->constrained('servicios')
                  ->onUpdate('cascade')
                  ->nullOnDelete();
        });
    }
};
