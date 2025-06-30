<?php

namespace App\Observers;

use App\Enums\ClienteEstadoEnum;
use App\Models\Cliente;
use App\Models\User;
use Filament\Notifications\Notification;

class ClienteObserver
{
    /**
     * Se ejecuta ANTES de guardar los cambios en la base de datos.
     * Aquí se modifican los atributos para que se guarden en una sola operación.
     */
    public function saving(Cliente $cliente): void
    {
        // Si se está asignando un asesor y el cliente estaba esperando esa asignación...
        if ($cliente->isDirty('asesor_id') && !is_null($cliente->asesor_id) && $cliente->getOriginal('estado') === ClienteEstadoEnum::PENDIENTE_ASIGNACION) {
            
            // ...modificamos el estado en el objeto. Se guardará junto con el asesor_id.
            $cliente->estado = ClienteEstadoEnum::ACTIVO;
        }
    }

    /**
     * Se ejecuta DESPUÉS de que los cambios se han guardado.
     * Lo usamos solo para los "efectos secundarios" como las notificaciones.
     */
    public function updated(Cliente $cliente): void
    {
        // Si el 'asesor_id' acaba de cambiar y el estado original era el correcto...
        if ($cliente->wasChanged('asesor_id') && !is_null($cliente->asesor_id) && $cliente->getOriginal('estado') === ClienteEstadoEnum::PENDIENTE_ASIGNACION) {
            
            // Notificamos al nuevo asesor asignado.
            $asesorAsignado = User::find($cliente->asesor_id);
            if ($asesorAsignado) {
                Notification::make()
                    ->title('¡Nuevo cliente asignado!')
                    ->body("Se te ha asignado el cliente '{$cliente->razon_social}'.")
                    ->sendToDatabase($asesorAsignado);
            }
        }
    }
}