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
         Schema::table('servicios', function (Blueprint $table) {
            $table->string('ciclo_facturacion', 20)
                ->nullable()
                ->after('precio_base')
                ->comment('Aplica solo a servicios recurrentes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn('ciclo_facturacion');
        });
    }
};
