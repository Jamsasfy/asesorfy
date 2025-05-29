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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            // Relación con User (si el cliente tiene acceso a la plataforma)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Relación con tipo de cliente (autónomo, SL, etc.)
            $table->foreignId('tipo_cliente_id')->constrained('tipo_clientes');

            // Datos personales o fiscales
            $table->string('nombre')->nullable();         // Para autónomos
            $table->string('apellidos')->nullable();      // Para autónomos
            $table->string('razon_social')->nullable();   // Para empresas
            $table->string('dni_cif');                    // Obligatorio

            // Contacto
            $table->string('email_contacto');
            $table->string('telefono_contacto');

            // Dirección
            $table->text('direccion')->nullable();
            $table->string('codigo_postal')->nullable();
            $table->string('localidad')->nullable();      
            $table->string('provincia');                  // Desde archivo provincias.php
            $table->string('comunidad_autonoma');         // Desde archivo provincias.php

            // Cuentas bancarias
            $table->string('iban_asesorfy')->nullable();      // Para cuotas
            $table->string('iban_impuestos')->nullable();     // Para impuestos
            $table->string('ccc')->nullable();                // Código Cuenta Cliente (opcional)

            // Asignación interna (usuarios del sistema)
            $table->foreignId('asesor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('coordinador_id')->nullable()->constrained('users')->nullOnDelete();

            // Otros
            $table->text('observaciones')->nullable();

            // Estado del cliente
            $table->string('estado')->default('pendiente');

            // Fechas de alta y baja
            $table->date('fecha_alta')->nullable();
            $table->date('fecha_baja')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
