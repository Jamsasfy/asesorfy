<?php

namespace App\Services;

use App\Models\ContadorFactura;
use App\Models\Factura;
use App\Models\ClienteSuscripcion; // <-- Asegúrate de que esta línea esté
use App\Enums\FacturaEstadoEnum; // <-- Asegúrate de que esta línea esté
use App\Enums\ClienteSuscripcionEstadoEnum; // <-- Asegúrate de que esta línea esté
use App\Enums\ServicioTipoEnum; // <-- Asegúrate de que esta línea esté
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacturacionService
{
    /**
     * Genera el siguiente número de factura usando la tabla de contadores.
     * Este método es usado tanto por facturas únicas como recurrentes.
     */
    public static function generarSiguienteNumeroFactura(): array
    {
        return DB::transaction(function () {
            $formato = ConfiguracionService::get('formato_factura', 'FR{YY}-00000');
            $prefijoSerie = substr($formato, 0, strpos($formato, '{'));

            $anoActual = Carbon::now()->year;

            $contador = ContadorFactura::lockForUpdate()->firstOrCreate(
                [
                    'serie' => $prefijoSerie,
                    'anio'  => $anoActual,
                ],
                [
                    'ultimo_numero' => 0
                ]
            );

            $nuevoNumero = $contador->ultimo_numero + 1;
            $contador->update(['ultimo_numero' => $nuevoNumero]);

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

    /**
     * Genera una factura para una ClienteSuscripcion de tipo UNICO.
     * La factura se generará como PAGADA y el metodo_pago como 'stripe'.
     * La suscripción se marcará como FINALIZADA.
     *
     * @param ClienteSuscripcion $suscripcion La suscripción única a facturar.
     * @return Factura|null La factura generada, o null si falla.
     */
    public static function generarFacturaParaSuscripcionUnica(ClienteSuscripcion $suscripcion): ?Factura
    {
        // Validamos que la suscripción sea de tipo UNICO y esté ACTIVA
        if ($suscripcion->servicio->tipo->value !== ServicioTipoEnum::UNICO->value || $suscripcion->estado !== ClienteSuscripcionEstadoEnum::ACTIVA) {
            Log::warning("Intento de facturar suscripción no única o no activa (ID: {$suscripcion->id}, Tipo: {$suscripcion->servicio->tipo->value}, Estado: {$suscripcion->estado->value})");
            return null;
        }

        return DB::transaction(function () use ($suscripcion) {
            try {
                // 1. Generar número de factura
                $datosFactura = self::generarSiguienteNumeroFactura();

                // 2. Definir la fecha de emisión (usamos la fecha de inicio de la suscripción, si existe, o la de hoy)
                $fechaEmision = $suscripcion->fecha_inicio ?? Carbon::now()->startOfDay();
                // Fecha de vencimiento (aunque sea pagada, es un dato útil)
                $fechaVencimiento = $fechaEmision->copy()->addDays(15); 

                // 3. Obtener la cantidad y calcular el precio unitario base real
                $cantidadSuscripcion = $suscripcion->cantidad;
                $precioUnitarioBaseReal = ($cantidadSuscripcion > 0) 
                                            ? ($suscripcion->precio_acordado / $cantidadSuscripcion) 
                                            : 0; 

                // 4. Construir la descripción para el ítem de la factura
                $descripcion = $suscripcion->nombre_final;
                $fechaInicioFormatted = $suscripcion->fecha_inicio ? $suscripcion->fecha_inicio->format('d/m/Y') : 'Fecha no especificada';
                $descripcion .= ' - ' . $fechaInicioFormatted; // Para servicios únicos, la fecha de inicio es relevante.
                if ($suscripcion->descuento_descripcion) {
                    $descripcion .= " ({$suscripcion->descuento_descripcion})";
                }

                // 5. Calcular precios y descuentos para el ítem de factura
                $precioUnitarioDespuesPorcentajeDto = $precioUnitarioBaseReal;
                $importeDescuentoAplicadoALinea = 0;
                $subtotalLineaBaseCalculado = $precioUnitarioBaseReal * $cantidadSuscripcion;

                // Comprobamos si el descuento está vigente en la fecha de emisión de la factura
                $descuentoVigente = $suscripcion->descuento_tipo && $suscripcion->descuento_valido_hasta && $fechaEmision->lte($suscripcion->descuento_valido_hasta);

                if ($descuentoVigente) {
                    if ($suscripcion->descuento_tipo === 'porcentaje') {
                        $descuentoPorcentajeValor = $suscripcion->descuento_valor / 100;
                        $precioUnitarioDespuesPorcentajeDto = $precioUnitarioBaseReal * (1 - $descuentoPorcentajeValor);
                        $importeDescuentoAplicadoALinea = ($precioUnitarioBaseReal - $precioUnitarioDespuesPorcentajeDto) * $cantidadSuscripcion;
                    } 
                }

                $precioUnitarioCalculadoParaFacturaItem = $precioUnitarioDespuesPorcentajeDto;
                $subtotalLineaCalculado = $precioUnitarioCalculadoParaFacturaItem * $cantidadSuscripcion;

                if ($descuentoVigente && in_array($suscripcion->descuento_tipo, ['fijo', 'precio_final'])) {
                    if ($suscripcion->descuento_tipo === 'fijo') {
                        $descuentoTotalFijo = $suscripcion->descuento_valor;
                        $subtotalLineaCalculado = max(0, $subtotalLineaBaseCalculado - $descuentoTotalFijo);
                        $importeDescuentoAplicadoALinea += $descuentoTotalFijo;
                    } else { // 'precio_final'
                        $precioFinalDeseado = $suscripcion->descuento_valor;
                        $descuentoTotalFijo = $subtotalLineaBaseCalculado - $precioFinalDeseado;
                        $subtotalLineaCalculado = $precioFinalDeseado;
                        $importeDescuentoAplicadoALinea = max(0, $descuentoTotalFijo);
                    }
                    $precioUnitarioCalculadoParaFacturaItem = ($cantidadSuscripcion > 0) ? ($subtotalLineaCalculado / $cantidadSuscripcion) : 0;
                } else {
                    $subtotalLineaCalculado = $subtotalLineaBaseCalculado;
                }

                // Obtener el porcentaje de IVA general de la configuración
                $ivaPorcentajeGeneral = ConfiguracionService::get('IVA_general', 21.00); 
                $ivaItemAmount = $subtotalLineaCalculado * ($ivaPorcentajeGeneral / 100);

                // 6. Crear la Factura en la base de datos
                $factura = Factura::create([
                    'cliente_id'        => $suscripcion->cliente_id,
                    'venta_id'          => $suscripcion->venta_origen_id, // Vinculamos a la Venta de origen
                    'serie'             => $datosFactura['serie'],
                    'numero_factura'    => $datosFactura['numero_factura'],
                    'estado'            => FacturaEstadoEnum::PAGADA, // <-- SIEMPRE PAGADA para Únicos
                    'metodo_pago'       => null,                 // <-- Método de pago 'stripe'
                    'fecha_emision'     => $fechaEmision,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'base_imponible'    => round($subtotalLineaCalculado, 2), // La base imponible de esta única línea
                    'total_iva'         => round($ivaItemAmount, 2),        // El IVA de esta única línea
                    'total_factura'     => round($subtotalLineaCalculado + $ivaItemAmount, 2), // El total de esta única línea
                ]);

                // 7. Crear la línea de FacturaItem
                $factura->items()->create([
                    'cliente_suscripcion_id' => $suscripcion->id,
                    'servicio_id'        => $suscripcion->servicio_id,
                    'descripcion'        => $descripcion,
                    'cantidad'           => $cantidadSuscripcion,
                    'precio_unitario'    => round($precioUnitarioBaseReal, 2),
                    'precio_unitario_aplicado' => round($precioUnitarioCalculadoParaFacturaItem, 2),
                    'importe_descuento'  => round($importeDescuentoAplicadoALinea, 2),
                    'porcentaje_iva'     => $ivaPorcentajeGeneral,
                    'subtotal'           => round($subtotalLineaCalculado, 2),
                ]);

                // 8. Marcar la suscripción como FINALIZADA
                $suscripcion->update(['estado' => ClienteSuscripcionEstadoEnum::FINALIZADA]);

                return $factura; // Devolvemos la factura creada

            } catch (\Exception $e) {
                Log::error("Error al generar factura única para suscripción ID {$suscripcion->id}: " . $e->getMessage());
                return null; // En caso de error, devolvemos null
            }
        });
    }
}