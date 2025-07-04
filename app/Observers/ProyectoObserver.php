<?php

namespace App\Observers;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Filament\Resources\ProyectoResource;
use App\Models\Proyecto;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action; // <-- Importante añadir este 'use'


class ProyectoObserver
{
    /**
     * Se ejecuta DESPUÉS de que un proyecto se ha actualizado.
     */
    public function updated(Proyecto $proyecto): void
    {
        // --- LÓGICA 1: Notificar al nuevo asesor asignado ---
        if ($proyecto->wasChanged('user_id') && !is_null($proyecto->user_id)) {
            $asesorAsignado = User::find($proyecto->user_id);
            if ($asesorAsignado) {
                Notification::make()
                    ->title('Te han asignado un nuevo proyecto')
                    ->body("Se te ha asignado el proyecto: '{$proyecto->nombre}'.")
                    ->icon('heroicon-o-briefcase')
                    ->actions([
                        Action::make('view')
                            ->label('Ver Proyecto')
                            ->url(ProyectoResource::getUrl('view', ['record' => $proyecto]))
                            ->markAsRead()->close(),
                    ])
                    ->sendToDatabase($asesorAsignado);
            }
        }

        // --- LÓGICA 2: Activar o cancelar suscripciones ---
        if ($proyecto->venta) {
            // Escenario A: El proyecto se ha FINALIZADO
            if ($proyecto->wasChanged('estado') && $proyecto->estado === ProyectoEstadoEnum::Finalizado) {
                // Esta función ya comprueba si quedan otros proyectos antes de activar.
                $proyecto->venta->checkAndActivateSubscriptions();
            }

            // Escenario B: El proyecto se ha CANCELADO
            if ($proyecto->wasChanged('estado') && $proyecto->estado === ProyectoEstadoEnum::Cancelado) {
                // ▼▼▼ LÓGICA CORREGIDA AQUÍ ▼▼▼

                // Comprobamos si todavía existen OTROS proyectos que no estén terminados.
                $otrosProyectosActivos = $proyecto->venta->proyectos()
                    ->where('id', '!=', $proyecto->id) // Excluimos el que acabamos de cancelar
                    ->whereNotIn('estado', [ProyectoEstadoEnum::Finalizado, ProyectoEstadoEnum::Cancelado])
                    ->exists();

                // SOLO si NO quedan otros proyectos, cancelamos las suscripciones.
                if (!$otrosProyectosActivos) {
                    $suscripcionesPendientes = $proyecto->venta->suscripciones()
                        ->where('estado', ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
                        ->get();
                    
                    foreach ($suscripcionesPendientes as $suscripcion) {
                        $suscripcion->update(['estado' => ClienteSuscripcionEstadoEnum::CANCELADA]);
                    }
                }
            }
        }
    
    }
}