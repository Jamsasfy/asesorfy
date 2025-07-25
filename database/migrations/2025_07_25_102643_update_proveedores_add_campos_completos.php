<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('tipo_nif')->nullable()->after('nif');
            $table->string('prefijo_intracomunitario')->nullable()->after('tipo_nif');
            $table->string('email_secundario')->nullable()->after('email');
            $table->string('persona_contacto')->nullable()->after('telefono');
            $table->boolean('tambien_cliente')->default(false)->after('persona_contacto');
            $table->string('tipo_operacion')->nullable()->after('tambien_cliente');
$table->foreignId('cuenta_contable_id')->nullable()->constrained('cuenta_clientes')->nullOnDelete()->after('tipo_operacion');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_nif',
                'prefijo_intracomunitario',
                'email_secundario',
                'persona_contacto',
                'tambien_cliente',
                'tipo_operacion',
            ]);
            $table->dropForeign(['cuenta_contable_id']);
            $table->dropColumn('cuenta_contable_id');
        });
    }
};
