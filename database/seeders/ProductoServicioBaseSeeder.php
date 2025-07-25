<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductoServicioBase;
use App\Models\CuentaCatalogo;

class ProductoServicioBaseSeeder extends Seeder
{
    public function run(): void
    {
        $servicios = [
            ['nombre' => 'Agua', 'codigo_cuenta' => '628000000002'],
            ['nombre' => 'Arrendamientos y cánones', 'codigo_cuenta' => '621000000000'],
            ['nombre' => 'Comidas (comedores)', 'codigo_cuenta' => '629000000005'],
            ['nombre' => 'Compras de mercaderías', 'codigo_cuenta' => '600000000000'],
            ['nombre' => 'Compras de otros aprovisionamientos', 'codigo_cuenta' => '602000000000'],
            ['nombre' => 'Correos, sellos (gastos de correo)', 'codigo_cuenta' => '629000000003'],
            ['nombre' => 'Gas', 'codigo_cuenta' => '628000000003'],
            ['nombre' => 'Gasolina, gasoil', 'codigo_cuenta' => '628000000001'],
            ['nombre' => 'Limpiezas', 'codigo_cuenta' => '629000000009'],
            ['nombre' => 'Luz (electricidad)', 'codigo_cuenta' => '628000000004'],
            ['nombre' => 'Mensajeros (servicios de mensajería)', 'codigo_cuenta' => '629000000010'],
            ['nombre' => 'Oficina (Compras de material de oficina)', 'codigo_cuenta' => '629000000011'],
            ['nombre' => 'Otros servicios', 'codigo_cuenta' => '629000000000'],
            ['nombre' => 'Primas de seguros', 'codigo_cuenta' => '625000000000'],
            ['nombre' => 'Publicidad, propaganda y relaciones públicas', 'codigo_cuenta' => '627000000000'],
            ['nombre' => 'Reparaciones y conservación', 'codigo_cuenta' => '622000000000'],
            ['nombre' => 'Servicios bancarios y similares', 'codigo_cuenta' => '626000000000'],
            ['nombre' => 'Servicios de profesionales independiente', 'codigo_cuenta' => '623000000000'],
            ['nombre' => 'Suministros', 'codigo_cuenta' => '628000000000'],
            ['nombre' => 'Teléfono (comunicaciones)', 'codigo_cuenta' => '629000000001'],
            ['nombre' => 'Transportes', 'codigo_cuenta' => '624000000000'],
            ['nombre' => 'Viajes (transporte de personal)', 'codigo_cuenta' => '629000000002'],
        ];

        foreach ($servicios as $servicio) {
            $cuenta = CuentaCatalogo::where('codigo', $servicio['codigo_cuenta'])->first();

            ProductoServicioBase::updateOrCreate(
                ['nombre' => $servicio['nombre']],
                [
                    'descripcion' => null,
                    'cuenta_catalogo_id' => $cuenta?->id,
                ]
            );
        }
    }
}
