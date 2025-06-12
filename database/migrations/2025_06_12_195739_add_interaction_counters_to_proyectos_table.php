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
         Schema::table('proyectos', function (Blueprint $table) {
            $table->unsignedInteger('llamadas')->default(0)->comment('Número de llamadas registradas para el proyecto.');
            $table->unsignedInteger('emails')->default(0)->comment('Número de emails registrados para el proyecto.');
            $table->unsignedInteger('chats')->default(0)->comment('Número de chats/mensajes registrados para el proyecto.');
            $table->unsignedInteger('otros_acciones')->default(0)->comment('Número de otras acciones registradas para el proyecto.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('proyectos', function (Blueprint $table) {
            $table->dropColumn(['llamadas', 'emails', 'chats', 'otros_acciones']);
        });
    }
};
