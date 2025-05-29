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
            // Si por algún motivo el FK existiera, MySQL lo 
            // quitaría junto con la columna. Solo borramos la columna.
            $table->dropColumn('lead_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('leads')
                ->onUpdate('cascade')
                ->nullOnDelete()
                ->after('id');
        });
    }
};
