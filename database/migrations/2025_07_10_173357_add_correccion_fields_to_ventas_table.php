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
         Schema::table('ventas', function (Blueprint $table) {
            // Lo añadimos después de la columna 'observaciones', por ejemplo
            $table->string('correccion_estado')->nullable()->after('observaciones');
            $table->timestamp('correccion_solicitada_at')->nullable()->after('correccion_estado');
            $table->foreignId('correccion_solicitada_por_id')->nullable()->after('correccion_solicitada_at')->constrained('users')->nullOnDelete();
            $table->text('correccion_motivo')->nullable()->after('correccion_solicitada_por_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['correccion_solicitada_por_id']);
            $table->dropColumn([
                'correccion_estado',
                'correccion_solicitada_at',
                'correccion_solicitada_por_id',
                'correccion_motivo',
            ]);
        });
    }
};
