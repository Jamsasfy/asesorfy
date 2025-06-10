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
        Schema::create('variables_configuracion', function (Blueprint $table) {
             $table->id(); // Columna ID auto-incremental (clave primaria)
            $table->string('nombre_variable')->unique(); // Nombre único para la variable (ej. IVA_general)
            $table->text('valor_variable'); // Valor de la variable (cifrado si es secreto)
            $table->string('tipo_dato', 50); // Tipo de dato para el valor (ej. 'cadena', 'numero_entero')
            $table->text('descripcion')->nullable(); // Descripción opcional de la variable
            $table->boolean('es_secreto')->default(false); // Indica si el valor es un secreto y debe cifrarse
            $table->timestamps(); // Columnas 'created_at' y 'updated_at' automáticas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variables_configuracion');
    }
};
