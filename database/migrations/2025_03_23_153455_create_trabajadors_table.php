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
        Schema::create('trabajadors', function (Blueprint $table) {
            $table->id();
            // Clave foránea a la tabla users (dato genérico de acceso)
            $table->unsignedBigInteger('user_id')->nullable();
            // Clave foránea a la tabla oficinas
            $table->unsignedBigInteger('oficina_id');
            
            $table->string('apellidos')->nullable();
            $table->string('telefono'); // Obligatorio
            $table->string('dni_o_cif'); //obligatorio
            $table->string('cargo')->nullable();
            $table->text('direccion')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('email_personal'); // Obligatorio
            $table->string('numero_seg_social')->nullable();
            $table->string('numero_cuenta_nomina')->nullable();
            $table->timestamps();

             // Definición de claves foráneas
          //  $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('oficina_id')->references('id')->on('oficinas')->onDelete('cascade');


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trabajadors');
    }
};
