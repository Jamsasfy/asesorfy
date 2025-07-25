<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CuentaCatalogo;
use App\Enums\TipoCuentaContable;

class CuentaCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $cuentas = [
            // --- INMOVILIZADO INTANGIBLE Y MATERIAL ---
            ['codigo' => '200000000000', 'descripcion' => 'Investigación'],
            ['codigo' => '201000000000', 'descripcion' => 'Desarrollo'],
            ['codigo' => '202000000000', 'descripcion' => 'Concesiones administrativas'],
            ['codigo' => '203000000000', 'descripcion' => 'Propiedad industrial'],
            ['codigo' => '204000000000', 'descripcion' => 'Fondo de comercio'],
            ['codigo' => '205000000000', 'descripcion' => 'Derechos de traspaso'],
            ['codigo' => '206000000000', 'descripcion' => 'Aplicaciones informáticas'],
            ['codigo' => '209000000000', 'descripcion' => 'Anticipos para inmovilizaciones intangibles'],
            ['codigo' => '210000000000', 'descripcion' => 'Terrenos y bienes naturales'],
            ['codigo' => '211000000000', 'descripcion' => 'Construcciones'],
            ['codigo' => '212000000000', 'descripcion' => 'Instalaciones técnicas'],
            ['codigo' => '213000000000', 'descripcion' => 'Maquinaria'],
            ['codigo' => '214000000000', 'descripcion' => 'Utillaje'],
            ['codigo' => '215000000000', 'descripcion' => 'Otras instalaciones'],
            ['codigo' => '216000000000', 'descripcion' => 'Mobiliario'],
            ['codigo' => '217000000000', 'descripcion' => 'Equipos para procesos de información'],
            ['codigo' => '218000000000', 'descripcion' => 'Elementos de transporte'],
            ['codigo' => '219000000000', 'descripcion' => 'Otro inmovilizado material'],

            // --- GASTOS ---
            ['codigo' => '566000000000', 'descripcion' => 'Depósitos constituidos a corto plazo (suplidos)'],
            ['codigo' => '600000000000', 'descripcion' => 'Compras de mercaderías'],
            ['codigo' => '601000000000', 'descripcion' => 'Compras de materias primas'],
            ['codigo' => '602000000000', 'descripcion' => 'Compras de otros aprovisionamientos'],
            ['codigo' => '607000000000', 'descripcion' => 'Trabajos realizados por otras empresas'],
            ['codigo' => '621000000000', 'descripcion' => 'Arrendamientos y cánones'],
            ['codigo' => '622000000000', 'descripcion' => 'Reparaciones y conservación'],
            ['codigo' => '623000000000', 'descripcion' => 'Servicios de profesionales independientes'],
            ['codigo' => '624000000000', 'descripcion' => 'Transportes'],
            ['codigo' => '625000000000', 'descripcion' => 'Primas de seguros'],
            ['codigo' => '626000000000', 'descripcion' => 'Servicios bancarios y similares'],
            ['codigo' => '627000000000', 'descripcion' => 'Publicidad, propaganda y relaciones públicas'],
            ['codigo' => '628000000000', 'descripcion' => 'Suministros'],
            ['codigo' => '628000000001', 'descripcion' => 'Gasolina'],
            ['codigo' => '628000000002', 'descripcion' => 'Agua'],
            ['codigo' => '628000000003', 'descripcion' => 'Gas'],
            ['codigo' => '628000000004', 'descripcion' => 'Luz (electricidad)'],
            ['codigo' => '629000000000', 'descripcion' => 'Otros servicios'],
            ['codigo' => '629000000001', 'descripcion' => 'Teléfono (comunicaciones)'],
            ['codigo' => '629000000002', 'descripcion' => 'Viajes (transporte de personal)'],
            ['codigo' => '629000000003', 'descripcion' => 'Correos, sellos (gastos de correo)'],
            ['codigo' => '629000000005', 'descripcion' => 'Comidas (comedores)'],
            ['codigo' => '629000000009', 'descripcion' => 'Limpiezas'],
            ['codigo' => '629000000010', 'descripcion' => 'Mensajeros (servicios de mensajería)'],
            ['codigo' => '629000000011', 'descripcion' => 'Oficina (Compras de material de oficina)'],
            ['codigo' => '631000000000', 'descripcion' => 'Otros tributos'],
            ['codigo' => '640000000000', 'descripcion' => 'Sueldos y salarios'],
            ['codigo' => '642000000000', 'descripcion' => 'Seguridad Social a cargo de la empresa y Cuota de autónomo'],
            ['codigo' => '662300000000', 'descripcion' => 'Intereses de deudas con entidades de crédito'],
            ['codigo' => '678000000000', 'descripcion' => 'Gastos excepcionales'],
            ['codigo' => '678100000000', 'descripcion' => 'Gastos no deducibles'],
            // ... otras cuentas ...
            ['codigo' => '400000000000', 'descripcion' => 'Proveedores (euros)'],
            // --- INGRESOS ---
            ['codigo' => '560000000000', 'descripcion' => 'Fianzas recibidas a corto plazo'],
            ['codigo' => '700000000000', 'descripcion' => 'Ventas de mercaderías'],
            ['codigo' => '705000000000', 'descripcion' => 'Prestaciones de servicios'],
            ['codigo' => '705000000001', 'descripcion' => 'ordenadores'],
            ['codigo' => '752000000000', 'descripcion' => 'Ingresos por arrendamientos'],
            ['codigo' => '769000000000', 'descripcion' => 'Otros ingresos financieros'],
            ['codigo' => '778000000000', 'descripcion' => 'Ingresos excepcionales'],
            ['codigo' => '740000000000', 'descripcion' => 'Subvenciones, donaciones y legados a la explotación'],
        ];

        foreach ($cuentas as $cuenta) {
            $grupo = substr($cuenta['codigo'], 0, 1);
            $tipo = match ($grupo) {
                '6' => TipoCuentaContable::GASTO,
                '7' => TipoCuentaContable::INGRESO,
                '5' => TipoCuentaContable::FINANCIERO,
                default => TipoCuentaContable::OTRO,
            };

            CuentaCatalogo::updateOrCreate(
                ['codigo' => $cuenta['codigo']],
                [
                    'descripcion' => $cuenta['descripcion'],
                    'grupo' => $grupo,
                    'subgrupo' => substr($cuenta['codigo'], 0, 2),
                    'nivel' => null,
                    'origen' => 'pgc',
                    'tipo' => $tipo,
                    'es_activa' => true,
                ]
            );
        }
    }
}
