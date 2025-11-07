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
    Schema::create('retenciones_irpf', function (Blueprint $table) {
        $table->id();
        $table->decimal('porcentaje', 5, 2); // ej. 15.00
        $table->string('descripcion')->nullable(); // descripción opcional
        $table->boolean('activo')->default(true);
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retencion_irpfs');
    }
};
