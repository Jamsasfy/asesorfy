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
        Schema::table('clientes', function (Blueprint $table) {
            // Añade la columna lead_id. Es nullable porque no todos los clientes vienen de un lead.
            // constrained() crea la clave foránea a la tabla 'leads'.
            // onUpdate('cascade') y nullOnDelete() son opciones comunes para manejar la eliminación del lead de origen.
            $table->foreignId('lead_id')
                  ->nullable()
                  ->constrained('leads')
                  ->onUpdate('cascade')
                  ->nullOnDelete()
                  ->after('id'); // <-- O después de otra columna existente, ej: after('nombre')
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Antes de eliminar la columna, es buena práctica eliminar la clave foránea explícitamente.
            $table->dropConstrainedForeignId('lead_id');
            // Luego elimina la columna.
            $table->dropColumn('lead_id');
        });
    }
};
