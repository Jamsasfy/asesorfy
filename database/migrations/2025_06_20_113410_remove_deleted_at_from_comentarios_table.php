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
        Schema::table('comentarios', function (Blueprint $table) {
            $table->dropSoftDeletes(); // elimina la columna 'deleted_at'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('comentarios', function (Blueprint $table) {
            $table->softDeletes(); // por si necesitas revertir
        });
    }
};
