<?php

namespace App\Observers;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Models\Proyecto;

class ProyectoObserver
{
    public function updated(Proyecto $proyecto): void
    {
        // Si el proyecto tiene una venta asociada...
        if ($proyecto->venta) {
            
            // Escenario 1: El proyecto se ha FINALIZADO
            if ($proyecto->wasChanged('estado') && $proyecto->estado === ProyectoEstadoEnum::Finalizado) {
                // Intentamos activar las suscripciones. El método interno ya comprueba si hay otros proyectos pendientes.
                $proyecto->venta->checkAndActivateSubscriptions();
            }

            // Escenario 2: El proyecto se ha CANCELADO
            if ($proyecto->wasChanged('estado') && $proyecto->estado === ProyectoEstadoEnum::Cancelado) { // Asumiendo que tienes este estado en ProyectoEstadoEnum
                // Buscamos todas las suscripciones de esa venta que aún estén pendientes...
                $suscripcionesPendientes = $proyecto->venta->suscripciones()
                    ->where('estado', ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
                    ->get();
                
                // ...y las cancelamos también.
                foreach ($suscripcionesPendientes as $suscripcion) {
                    $suscripcion->update(['estado' => ClienteSuscripcionEstadoEnum::CANCELADA]);
                }
            }
        }
    }
}