<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClienteSuscripcion;
use App\Models\Factura;
use App\Services\FacturacionService;
use App\Services\ConfiguracionService;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Enums\FacturaEstadoEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FacturacionMensualGenerar extends Command
{
    protected $signature = 'facturacion:generar {--fecha=}';
    protected $description = 'Genera las facturas mensuales para las suscripciones activas.';

    public function handle(): void
    {
        $hoy = $this->option('fecha') ? Carbon::parse($this->option('fecha'))->startOfDay() : Carbon::now()->startOfDay();
        $this->info("Iniciando facturación para el día: {$hoy->toDateString()}");

        $suscripciones = ClienteSuscripcion::query()
            ->where('estado', ClienteSuscripcionEstadoEnum::ACTIVA)
            ->whereHas('servicio', fn($q) => $q->whereIn('tipo', [ServicioTipoEnum::RECURRENTE, ServicioTipoEnum::UNICO]))
            ->whereDate('proxima_fecha_facturacion', '<=', $hoy)
            ->get()
            ->groupBy('cliente_id');

        if ($suscripciones->isEmpty()) {
            $this->info('No hay suscripciones para facturar hoy.');
            return;
        }

        $this->info("Se encontraron suscripciones para {$suscripciones->count()} clientes.");
        $ivaPorcentaje = ConfiguracionService::get('IVA_general', 21.00); 

        foreach ($suscripciones as $suscripcionesDelCliente) {
            DB::transaction(function () use ($suscripcionesDelCliente, $hoy, $ivaPorcentaje) {
                
                $datosFactura = FacturacionService::generarSiguienteNumeroFactura();

                $factura = Factura::create([
                    'cliente_id'        => $suscripcionesDelCliente->first()->cliente_id,
                    'serie'             => $datosFactura['serie'],
                    'numero_factura'    => $datosFactura['numero_factura'],
                    'estado'            => FacturaEstadoEnum::PENDIENTE_PAGO,
                    'fecha_emision'     => $hoy,
                    'fecha_vencimiento' => $hoy->copy()->addDays(15),
                    'base_imponible'    => 0, 'total_iva' => 0, 'total_factura' => 0,
                ]);

                $this->line(" -> Creada Factura {$factura->numero_factura} para el cliente ID {$factura->cliente_id}");

                $baseTotal = 0;
                $ivaTotal = 0;

                foreach ($suscripcionesDelCliente as $suscripcion) {
                    $precioUnitarioBaseReal = ($suscripcion->cantidad > 0) 
                                                ? ($suscripcion->precio_acordado / $suscripcion->cantidad) 
                                                : 0; 
                    $cantidadSuscripcion = $suscripcion->cantidad;
                    
                    // ** CAMBIO AQUÍ: Lógica para la descripción **
                    $descripcion = $suscripcion->nombre_final;
                    if ($suscripcion->servicio->tipo === ServicioTipoEnum::RECURRENTE) {
                        $descripcion .= ' - Periodo ' . $hoy->format('m/Y');
                    } elseif ($suscripcion->servicio->tipo === ServicioTipoEnum::UNICO) {
                        // Usamos la fecha de inicio de la suscripción única para su descripción
                        $fechaInicioFormatted = $suscripcion->fecha_inicio ? $suscripcion->fecha_inicio->format('d/m/Y') : 'Fecha no especificada';
                        $descripcion .= ' - ' . $fechaInicioFormatted;
                    }
                    // Añadimos la descripción del descuento si existe
                    if ($suscripcion->descuento_descripcion) {
                        $descripcion .= " ({$suscripcion->descuento_descripcion})";
                    }
                    // ** FIN CAMBIO Lógica para la descripción **


                    $precioUnitarioDespuesPorcentajeDto = $precioUnitarioBaseReal;
                    $importeDescuentoAplicadoALinea = 0;
                    
                    $descuentoVigente = $suscripcion->descuento_tipo && $suscripcion->descuento_valido_hasta && $hoy->lte($suscripcion->descuento_valido_hasta);

                    if ($descuentoVigente) {
                        if ($suscripcion->descuento_tipo === 'porcentaje') {
                            $descuentoPorcentajeValor = $suscripcion->descuento_valor / 100;
                            $precioUnitarioDespuesPorcentajeDto = $precioUnitarioBaseReal * (1 - $descuentoPorcentajeValor);
                            $importeDescuentoAplicadoALinea = ($precioUnitarioBaseReal - $precioUnitarioDespuesPorcentajeDto) * $cantidadSuscripcion;
                        } 
                    }
                    
                    $precioUnitarioCalculadoParaFacturaItem = $precioUnitarioDespuesPorcentajeDto;
                    $subtotalLineaBaseCalculado = $precioUnitarioCalculadoParaFacturaItem * $cantidadSuscripcion;

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

                    $ivaItem = $subtotalLineaCalculado * ($ivaPorcentaje / 100);

                    $factura->items()->create([
                        'cliente_suscripcion_id' => $suscripcion->id,
                        'servicio_id'        => $suscripcion->servicio_id, 
                        'descripcion'        => $descripcion,
                        'cantidad'           => $cantidadSuscripcion,
                        'precio_unitario'    => round($precioUnitarioBaseReal, 2),
                        'precio_unitario_aplicado' => round($precioUnitarioCalculadoParaFacturaItem, 2),
                        'importe_descuento'  => round($importeDescuentoAplicadoALinea, 2),
                        'porcentaje_iva'     => $ivaPorcentaje,
                        'subtotal'           => round($subtotalLineaCalculado, 2),
                    ]);

                    $baseTotal += $subtotalLineaCalculado;
                    $ivaTotal += $ivaItem;

                    if ($suscripcion->servicio->tipo === ServicioTipoEnum::RECURRENTE) {
                        $suscripcion->update([
                            'proxima_fecha_facturacion' => $suscripcion->proxima_fecha_facturacion->copy()->addMonth()
                        ]);
                    } elseif ($suscripcion->servicio->tipo === ServicioTipoEnum::UNICO) {
                        $suscripcion->update([
                            'estado' => ClienteSuscripcionEstadoEnum::FINALIZADA
                        ]);
                    }
                }

                $factura->update([
                    'base_imponible' => round($baseTotal, 2),
                    'total_iva'      => round($ivaTotal, 2),
                    'total_factura'  => round($baseTotal + $ivaTotal, 2),
                ]);
            });
        }

        $this->info('¡Proceso de facturación finalizado!');
    }
}