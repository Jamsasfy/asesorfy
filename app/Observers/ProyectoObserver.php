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
        // Si el campo 'user_id' (el asesor) acaba de cambiar y no es nulo...
        if ($proyecto->wasChanged('user_id') && !is_null($proyecto->user_id)) {
            
            $asesorAsignado = User::find($proyecto->user_id);

            if ($asesorAsignado) {
                // Y le enviamos la notificación
                Notification::make()
                    ->title('Te han asignado un nuevo proyecto')
                    ->body("Se te ha asignado el proyecto: '{$proyecto->nombre}'.")
                    ->icon('heroicon-o-briefcase')                   
                    ->actions([
                        Action::make('view')
                            ->label('Ver Proyecto')
                            ->url(ProyectoResource::getUrl('view', ['record' => $proyecto]))
                            ->markAsRead(), // Opcional: marca la notificación como leída al hacer clic
                           
                    ])
                    ->sendToDatabase($asesorAsignado);
            }
        }

        // --- LÓGICA 2: Activar o cancelar suscripciones ---
        if ($proyecto->venta) {
            // Escenario A: El proyecto se ha FINALIZADO
            if ($proyecto->wasChanged('estado') && $proyecto->estado === ProyectoEstadoEnum::Finalizado) {
                $proyecto->venta->checkAndActivateSubscriptions();
            }

            // Escenario B: El proyecto se ha CANCELADO
            if ($proyecto->wasChanged('estado') && $proyecto->estado === ProyectoEstadoEnum::Cancelado) {
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