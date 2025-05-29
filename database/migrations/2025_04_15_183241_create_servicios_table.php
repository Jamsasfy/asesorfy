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
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Nombre del servicio (Ej: "Iguala Básica Autónomo", "Declaración Renta", "Creación SL")
            $table->text('descripcion')->nullable(); // Descripción más detallada
            $table->string('tipo'); // Almacenará 'unico' o 'recurrente' (usaremos un Enum en el modelo)
            $table->decimal('precio_base', 10, 2)->default(0); // Precio estándar del servicio
            $table->boolean('activo')->default(true); // Para poder desactivar servicios sin borrarlos
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
