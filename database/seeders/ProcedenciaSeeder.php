<?php

namespace Database\Seeders;

use App\Models\Procedencia; // Importa tu modelo
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProcedenciaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Asegura que exista la procedencia 'Solicitud Interna Cliente'
        Procedencia::updateOrCreate(
            ['key' => 'solicitud_interna'], // Busca por esta clave única
            [ // Valores para crearla si no existe, o para actualizarla si ya existe (excepto la key)
                'procedencia' => 'Solicitud Interna Cliente',
                'descripcion' => 'Lead generado a partir de una petición de un cliente ya existente.',
                'activo' => true,
            ]
        );

      

         // Añade más si es necesario...
    }
}