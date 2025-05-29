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
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();

            // Nombre del documento (sin extensión)
            $table->string('nombre');

            // Usuario que sube el documento
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Cliente al que pertenece el documento
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');

            // Ruta del archivo y su tipo MIME
            $table->string('ruta');
            $table->string('mime_type')->nullable();

            // Tipo y subtipo de documento
            $table->foreignId('tipo_documento_id')->constrained('documento_categorias')->onDelete('cascade');
            $table->foreignId('subtipo_documento_id')->constrained('documento_subtipos')->onDelete('cascade');

            // Estado de verificación y observaciones
            $table->boolean('verificado')->default(false);
            $table->text('observaciones')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
