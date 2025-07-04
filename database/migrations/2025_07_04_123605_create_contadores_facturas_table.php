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
        Schema::create('contadores_facturas', function (Blueprint $table) {
            $table->id();
            $table->string('serie'); // Ej: 'FRA', 'FRR'
            $table->year('anio');
            $table->integer('ultimo_numero');
            $table->timestamps();

            $table->unique(['serie', 'anio']); // Combinación única para evitar duplicados
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contadores_facturas');
    }
};
