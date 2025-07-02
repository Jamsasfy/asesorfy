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
    Schema::table('departamentos', function (Blueprint $table) {
        $table->foreignId('coordinador_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('departamentos', function (Blueprint $table) {
            // Elimina la clave foránea y la columna
            $table->dropForeign(['coordinador_id']);
            $table->dropColumn('coordinador_id');
        });
    }
};
