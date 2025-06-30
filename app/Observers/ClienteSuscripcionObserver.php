<?php

namespace App\Observers;

use App\Enums\ClienteEstadoEnum;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Models\ClienteSuscripcion;
use App\Models\User;
use Filament\Notifications\Notification;

class ClienteSuscripcionObserver
{
    public function updated(ClienteSuscripcion $suscripcion): void
    {
        // Si la suscripción principal se ha activado...
        if ($suscripcion->wasChanged('estado') && $suscripcion->estado === ClienteSuscripcionEstadoEnum::ACTIVA && $suscripcion->es_tarifa_principal) {

            $cliente = $suscripcion->cliente;

            if ($cliente && $cliente->estado !== ClienteEstadoEnum::ACTIVO) {
                // 1. Establecemos la fecha de alta y lo ponemos pendiente de asignación
                $cliente->update([
                    'estado' => ClienteEstadoEnum::PENDIENTE_ASIGNACION,
                    'fecha_alta' => now(),
                ]);

                // 2. Notificamos a los coordinadores
                $coordinadores = User::whereHas('roles', fn ($q) => $q->where('name', 'coordinador'))->get();
               if ($coordinadores->isNotEmpty()) {
                        Notification::make()
                            ->title('Cliente listo para asignar')
                            ->body("El cliente '{$cliente->razon_social}' necesita un asesor.")
                            ->actions([\Filament\Notifications\Actions\Action::make('view')->label('Ver Cliente')->url(\App\Filament\Resources\ClienteResource::getUrl('edit', ['record' => $cliente]))])
                            ->sendToDatabase($coordinadores); // <-- MÉTODO CORRECTO
                    }
            }
        }
    }
}