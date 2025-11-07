<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('registro_facturas', function (Blueprint $table) {
            // Eliminamos las columnas polimórficas si existen
            if (Schema::hasColumn('registro_facturas', 'facturable_id')) {
                $table->dropColumn('facturable_id');
            }

            if (Schema::hasColumn('registro_facturas', 'facturable_type')) {
                $table->dropColumn('facturable_type');
            }

            // Añadir proveedor_id si no existe
            if (!Schema::hasColumn('registro_facturas', 'proveedor_id')) {
                $table->foreignId('proveedor_id')->nullable()->constrained()->nullOnDelete();
            }

            // Añadir cliente_final_id si no existe
            if (!Schema::hasColumn('registro_facturas', 'cliente_final_id')) {
                $table->foreignId('cliente_final_id')->nullable()->constrained('clientes_finales')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('registro_facturas', function (Blueprint $table) {
            if (Schema::hasColumn('registro_facturas', 'proveedor_id')) {
                $table->dropForeign(['proveedor_id']);
                $table->dropColumn('proveedor_id');
            }

            if (Schema::hasColumn('registro_facturas', 'cliente_final_id')) {
                $table->dropForeign(['cliente_final_id']);
                $table->dropColumn('cliente_final_id');
            }

            // Restauramos relación polimórfica si fuera necesario
            if (!Schema::hasColumn('registro_facturas', 'facturable_id') && !Schema::hasColumn('registro_facturas', 'facturable_type')) {
                $table->morphs('facturable');
            }
        });
    }
};
