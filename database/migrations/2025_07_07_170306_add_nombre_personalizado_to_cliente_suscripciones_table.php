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
        $table->string('nombre_personalizado')->nullable()->after('servicio_id');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cliente_suscripciones', function (Blueprint $table) {
                    $table->dropColumn('nombre_personalizado');

        });
    }
};
