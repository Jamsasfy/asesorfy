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
        Schema::table('documentos', function (Blueprint $table) {
            $table->unsignedBigInteger('documentable_id')->nullable()->after('id');
            $table->string('documentable_type')->nullable()->after('documentable_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn(['documentable_id', 'documentable_type']);
        });
    }
};
