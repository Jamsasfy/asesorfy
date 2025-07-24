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
        Schema::create('cuentas_catalogo', function (Blueprint $table) {
    $table->id();
    $table->string('codigo')->unique(); // ej: 629000000000
    $table->string('descripcion');      // ej: Otros servicios
    $table->string('grupo');            // ej: 6
    $table->string('subgrupo')->nullable(); // ej: 62
    $table->string('nivel')->nullable();    // si necesitas jerarquía
    $table->string('origen')->default('pgc'); // base oficial o cliente
    $table->enum('tipo', ['gasto', 'ingreso', 'financiero', 'otro']);
    $table->boolean('es_activa')->default(true);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuentas_catalogo');
    }
};
