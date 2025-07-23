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
        Schema::rename('cliente_finales', 'clientes_finales');
    }

    public function down(): void
    {
        Schema::rename('clientes_finales', 'cliente_finales');
    }
};
