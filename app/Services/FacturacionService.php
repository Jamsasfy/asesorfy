<?php

namespace App\Services;

use App\Models\ContadorFactura;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FacturacionService
{
    /**
     * Genera el siguiente número de factura usando la tabla de contadores.
     */
    public static function generarSiguienteNumeroFactura(): array
    {
        return DB::transaction(function () {
            // 1. Obtenemos el formato de la configuración.
            $formato = ConfiguracionService::get('formato_factura', 'FR{YY}-00000');
            $prefijoSerie = substr($formato, 0, strpos($formato, '{')); // Ej: "FR"

            // 2. Obtenemos el año actual.
            $anoActual = Carbon::now()->year;

            // 3. Buscamos o creamos el contador para esta serie y año, y lo bloqueamos.
            $contador = ContadorFactura::lockForUpdate()->firstOrCreate(
                [
                    'serie' => $prefijoSerie,
                    'anio'  => $anoActual,
                ],
                [
                    'ultimo_numero' => 0
                ]
            );

            // 4. Incrementamos el número y lo guardamos.
            $nuevoNumero = $contador->ultimo_numero + 1;
            $contador->update(['ultimo_numero' => $nuevoNumero]);

            // 5. Construimos el número de factura final con el formato.
            $anoDosDigitos = Carbon::now()->format('y');
            $serieCompleta = "{$prefijoSerie}{$anoDosDigitos}-";
            $padding = strlen(substr($formato, strrpos($formato, '-') + 1));
            $numeroConPadding = str_pad($nuevoNumero, $padding, '0', STR_PAD_LEFT);

            return [
                'serie' => $serieCompleta,
                'numero_factura' => $serieCompleta . $numeroConPadding,
            ];
        });
    }
}