<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuenta_clientes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();

            // Opcional: de qué cuenta base parte
            $table->foreignId('cuenta_catalogo_id')->nullable()->constrained('cuentas_catalogo')->nullOnDelete();

            $table->string('codigo')->unique(); // Ej: 628000000005
            $table->string('descripcion');      // Ej: "Luz oficina Madrid"
            $table->boolean('es_activa')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuenta_clientes');
    }
};
