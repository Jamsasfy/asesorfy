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
       // Modifica la tabla existente 'procedencias'
       Schema::table('procedencias', function (Blueprint $table) {
        // Añade la columna 'key' después de la columna 'procedencia'
        $table->string('key')
              ->unique()
              ->nullable()
              ->after('procedencia'); // Colocarla después de tu campo existente
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revierte el cambio si haces rollback
        Schema::table('procedencias', function (Blueprint $table) {
            // Asegúrate de que el nombre de la columna es correcto si usas SQLite
            // $table->dropUnique(['key']); // Puede ser necesario en algunos gestores
            $table->dropColumn('key');
        });
    }
};
