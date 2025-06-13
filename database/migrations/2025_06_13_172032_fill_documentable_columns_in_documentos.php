<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         DB::table('documentos')
        ->whereNotNull('cliente_id')
        ->update([
            'documentable_id' => DB::raw('cliente_id'),
            'documentable_type' => 'App\Models\Cliente',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       DB::table('documentos')->update([
            'documentable_id' => null,
            'documentable_type' => null,
        ]);
    }
};
