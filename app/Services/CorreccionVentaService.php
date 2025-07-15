<?php

namespace App\Services;

use App\Enums\FacturaEstadoEnum;
use App\Models\Factura;
use App\Models\FacturaItem;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CorreccionVentaService
{
   /**
     * Procesa la corrección de una venta, anulando facturas viejas,
     * creando abonos, actualizando suscripciones y generando la nueva factura.
     */
    public static function procesar(Venta $venta): void
    {
         

        DB::transaction(function () use ($venta) {

            // --- 1. ANULAR Y ABONAR LO ANTIGUO ---
            $facturasOriginales = $venta->facturas()->where('estado', '!=', FacturaEstadoEnum::ANULADA)->get();
            foreach ($facturasOriginales as $facturaOriginal) {
                $facturaOriginal->update(['estado' => FacturaEstadoEnum::ANULADA]);
                self::crearFacturaRectificativaPara($facturaOriginal);
            }

            // --- 2. BORRAR LAS SUSCRIPCIONES ANTIGUAS ---
            $suscripcionIds = $venta->suscripciones()->pluck('id');
            if ($suscripcionIds->isNotEmpty()) {
                FacturaItem::whereIn('cliente_suscripcion_id', $suscripcionIds)->update(['cliente_suscripcion_id' => null]);
            }
            $venta->suscripciones()->delete();

            // --- 3. RE-CREAR SUSCRIPCIONES Y PROYECTOS (SIN FACTURAR) ---
            $venta->processSaleAfterCreation();
            
            // --- 4. CREAR LA NUEVA FACTURA CONSOLIDADA Y CORRECTA ---
            FacturacionService::generarFacturaParaVenta($venta);
        });
    }

    protected static function crearFacturaRectificativaPara(Factura $facturaOriginal): void
    {
        $datosNuevaFactura = FacturacionService::generarSiguienteNumeroFactura('rectificativa');
        $facturaRectificativa = Factura::create([
            'cliente_id' => $facturaOriginal->cliente_id,
            'venta_id' => $facturaOriginal->venta_id,
            'serie' => $datosNuevaFactura['serie'],
            'numero_factura' => $datosNuevaFactura['numero_factura'],
            'fecha_emision' => now(),
            'fecha_vencimiento' => now(),
            'estado' => FacturaEstadoEnum::PAGADA,
            'base_imponible' => -$facturaOriginal->base_imponible,
            'total_iva' => -$facturaOriginal->total_iva,
            'total_factura' => -$facturaOriginal->total_factura,
            'factura_rectificada_id' => $facturaOriginal->id,
            'motivo_rectificacion' => 'Corrección por modificación de la venta original.',
        ]);

        foreach ($facturaOriginal->items as $item) {
           $facturaRectificativa->items()->create([
    'descripcion' => $item->descripcion . ' (Factura Rectificada ' . $facturaOriginal->numero_factura . ')',
    'cantidad' => $item->cantidad,
    'precio_unitario' => -$item->precio_unitario,
    'precio_unitario_aplicado' => -$item->precio_unitario_aplicado,
    'importe_descuento' => -$item->importe_descuento,
    'porcentaje_iva' => $item->porcentaje_iva,
    'subtotal' => -$item->subtotal,
    'descuento_tipo' => $item->descuento_tipo,
    'descuento_valor' => $item->descuento_valor,
    'cliente_suscripcion_id' => $item->cliente_suscripcion_id,
    'servicio_id' => $item->servicio_id,
]);
        }
    }
}