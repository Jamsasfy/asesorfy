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
            $table->boolean('requiere_proyecto_activacion')->default(false)->after('tipo')
                  ->comment('Indica si la activación de este servicio recurrente requiere la creación de un proyecto.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn('requiere_proyecto_activacion');
        });
    }
};
