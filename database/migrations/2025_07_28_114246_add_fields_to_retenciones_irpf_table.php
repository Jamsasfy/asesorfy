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
         Schema::table('retencion_irpfs', function (Blueprint $table) {
            $table->decimal('porcentaje', 5, 2)->nullable()->after('id');
            $table->string('descripcion')->nullable()->after('porcentaje');
            $table->boolean('activo')->default(true)->after('descripcion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('retencion_irpfs', function (Blueprint $table) {
            $table->dropColumn(['porcentaje', 'descripcion', 'activo']);
        });
    }
};
