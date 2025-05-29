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
            $table->foreignId('lead_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('leads')
                  ->nullOnDelete();

            $table->foreignId('comercial_id')
                  ->nullable()
                  ->after('lead_id')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // En orden inverso al creado
            $table->dropConstrainedForeignId('comercial_id');
            $table->dropConstrainedForeignId('lead_id');
        });
    }
};
