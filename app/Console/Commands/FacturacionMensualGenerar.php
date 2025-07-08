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
                    $cantidadSuscripcion = $suscripcion->cantidad; // La cantidad de la suscripción (ej. 11)
                    // ** ¡NUEVO CÁLCULO CLAVE! **
                    // Derivamos el precio unitario base real a partir del precio acordado TOTAL
                    $precioUnitarioBaseReal = ($cantidadSuscripcion > 0) 
                                                ? ($suscripcion->precio_acordado / $cantidadSuscripcion) 
                                                : 0; 
                    
                    $descripcion = $suscripcion->nombre_final . ' - Periodo ' . $hoy->format('m/Y');
                    if ($suscripcion->descuento_descripcion) {
                        $descripcion .= " ({$suscripcion->descuento_descripcion})";
                    }

                    $precioUnitarioDespuesPorcentajeDto = $precioUnitarioBaseReal; // Usamos el precio unitario base REAL
                    $importeDescuentoAplicadoALinea = 0; // Descuento monetario total aplicado a esta línea de la factura
                    
                    $descuentoVigente = $suscripcion->descuento_tipo && $suscripcion->descuento_valido_hasta && $hoy->lte($suscripcion->descuento_valido_hasta);

                    if ($descuentoVigente) {
                        if ($suscripcion->descuento_tipo === 'porcentaje') {
                            $descuentoPorcentajeValor = $suscripcion->descuento_valor / 100;
                            $precioUnitarioDespuesPorcentajeDto = $precioUnitarioBaseReal * (1 - $descuentoPorcentajeValor);
                            $importeDescuentoAplicadoALinea = ($precioUnitarioBaseReal - $precioUnitarioDespuesPorcentajeDto) * $cantidadSuscripcion;
                        } 
                    }
                    
                    // Precio unitario base para cálculos posteriores
                    $precioUnitarioCalculadoParaFacturaItem = $precioUnitarioDespuesPorcentajeDto; // Este es el que se ajustará con descuentos fijos/precio_final
                    $subtotalLineaBaseCalculado = $precioUnitarioCalculadoParaFacturaItem * $cantidadSuscripcion; // Subtotal de la línea con descuentos porcentuales

                    // Lógica para aplicar descuentos FIJOS o de PRECIO_FINAL al subtotal de la línea
                    if ($descuentoVigente && in_array($suscripcion->descuento_tipo, ['fijo', 'precio_final'])) {
                        if ($suscripcion->descuento_tipo === 'fijo') {
                            $descuentoTotalFijo = $suscripcion->descuento_valor; // Este valor ya es el total para la línea
                            $subtotalLineaCalculado = max(0, $subtotalLineaBaseCalculado - $descuentoTotalFijo);
                            $importeDescuentoAplicadoALinea += $descuentoTotalFijo; // Sumar al descuento total de la línea
                        } else { // 'precio_final'
                            $precioFinalDeseado = $suscripcion->descuento_valor; // Este valor es el precio final TOTAL para la línea
                            $descuentoTotalFijo = $subtotalLineaBaseCalculado - $precioFinalDeseado;
                            $subtotalLineaCalculado = $precioFinalDeseado; // El precio final es el nuevo subtotal
                            $importeDescuentoAplicadoALinea = max(0, $descuentoTotalFijo); // El descuento es la diferencia
                        }
                        // Recalcular el precio unitario aplicado después de descuento fijo/precio_final
                        $precioUnitarioCalculadoParaFacturaItem = ($cantidadSuscripcion > 0) ? ($subtotalLineaCalculado / $cantidadSuscripcion) : 0;
                    } else {
                        // Si no hay descuento fijo/precio_final, el subtotal final de la línea es el subtotal base calculado
                        $subtotalLineaCalculado = $subtotalLineaBaseCalculado;
                    }

                    // Calcular IVA sobre el subtotal final de la línea
                    $ivaItem = $subtotalLineaCalculado * ($ivaPorcentaje / 100);

                    $factura->items()->create([
                        'cliente_suscripcion_id' => $suscripcion->id,
                        'descripcion'        => $descripcion,
                        'cantidad'           => $cantidadSuscripcion, // <-- ¡Cantidad real de la suscripción!
                        'precio_unitario'    => round($precioUnitarioBaseReal, 2),          // <-- Precio UNITARIO ORIGINAL REAL
                        'precio_unitario_aplicado' => round($precioUnitarioCalculadoParaFacturaItem, 2), // <-- Precio UNITARIO FINAL con descuentos
                        'importe_descuento'  => round($importeDescuentoAplicadoALinea, 2), // Descuento TOTAL aplicado a la línea
                        'porcentaje_iva'     => $ivaPorcentaje,
                        'subtotal'           => round($subtotalLineaCalculado, 2),       // Subtotal FINAL de la línea (cantidad * precio_unitario_aplicado)
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