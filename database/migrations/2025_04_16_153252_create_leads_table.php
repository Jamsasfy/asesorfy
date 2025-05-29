<?php

use App\Enums\LeadEstadoEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

             // Tablas requeridas: users, procedencias, clientes, servicios, motivos_descarte


    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('email')->nullable();
            $table->string('tfn');
            $table->foreignId('procedencia_id')->nullable()->constrained('procedencias')->onUpdate('cascade')->nullOnDelete();
            $table->foreignId('creado_id')->nullable()->constrained('users')->onUpdate('cascade')->nullOnDelete();
            $table->foreignId('asignado_id')->nullable()->constrained('users')->onUpdate('cascade')->nullOnDelete();
            $table->string('estado')->default(LeadEstadoEnum::SIN_GESTIONAR->value)->index();
            $table->text('demandado')->nullable();
            $table->dateTime('fecha_gestion')->nullable();
            $table->dateTime('agenda')->nullable();
            $table->dateTime('fecha_cierre')->nullable();
            $table->text('observacion_cierre')->nullable();
            $table->foreignId('motivo_descarte_id')->nullable()->constrained('motivos_descarte')->onUpdate('cascade')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->onUpdate('cascade')->nullOnDelete()->index();
          //  $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->onUpdate('cascade')->nullOnDelete()->index(); LO HAREMOS EN ADD CUANTO PROYECTO ESTE CREADO
            $table->foreignId('servicio_propuesto_id')->nullable()->constrained('servicios')->onUpdate('cascade')->nullOnDelete();

            // --- Contadores de Interacción ---
            $table->integer('llamadas')->default(0);
            $table->integer('emails')->default(0);
            $table->integer('chats')->default(0);
            $table->integer('otros_acciones')->default(0);
            // --- Fin Contadores ---

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
