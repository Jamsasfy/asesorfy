<?php

namespace App\Observers;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Models\ClienteSuscripcion;
use App\Models\Proyecto;
use App\Models\Venta;
use Illuminate\Validation\ValidationException;

class VentaObserver
{
    /**
     * Handle the Venta "creating" event.
     * Se ejecuta ANTES de crear la venta. Ideal para validaciones.
     */
    public function creating(Venta $venta): void
    {
        // Esta es la validación que tenías en el modelo Venta.
        // La movemos aquí para mantener el modelo limpio.
        foreach ($venta->items as $item) {
            $servicio = $item->servicio;
            if ($servicio && $servicio->tipo->value === ServicioTipoEnum::RECURRENTE->value && $servicio->es_tarifa_principal) {
                $existeSuscripcion = ClienteSuscripcion::where('cliente_id', $venta->cliente_id)
                    ->where('es_tarifa_principal', true)
                    ->whereIn('estado', [
                        ClienteSuscripcionEstadoEnum::ACTIVA->value,
                        ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION->value,
                    ])
                    ->exists();

                if ($existeSuscripcion) {
                    throw ValidationException::withMessages([
                        'items' => "El cliente ya tiene una tarifa principal activa o pendiente. No se puede vender otra.",
                    ]);
                }
            }
        }
    }

    
}