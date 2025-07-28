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
        Schema::create('tipos_iva', function (Blueprint $table) {
        $table->id();
        $table->decimal('porcentaje', 5, 2);
        $table->decimal('recargo_equivalencia', 5, 2)->default(0);
        $table->string('descripcion')->nullable();
        $table->boolean('activo')->default(true);
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_ivas');
    }
};
