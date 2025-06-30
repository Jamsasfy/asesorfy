<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Filament\Resources\VentaResource;
use App\Models\ClienteSuscripcion;
use App\Models\Proyecto;
use App\Models\Servicio;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Actions\Action as NotifyAction;

use App\Models\Venta;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class CreateVenta extends CreateRecord
{
    protected static string $resource = VentaResource::class;

   
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
   
 protected function beforeCreate(): void
    {
        // 1) Obtenemos TODOS los datos del formulario
        $data  = $this->form->getState();
        $clienteId = $data['cliente_id'] ?? null;
        $items     = $data['items']     ?? [];

        if ($clienteId) {
            foreach ($items as $i => $item) {
                $servicioId = $item['servicio_id'] ?? null;
                if (! $servicioId) {
                    continue;
                }

                $servicio = Servicio::find($servicioId);

                // 2) Sólo nos importa el recurrente-principal
                if (
                    $servicio
                    && $servicio->tipo->value === ServicioTipoEnum::RECURRENTE->value
                    && $servicio->es_tarifa_principal
                ) {
                    $existe = ClienteSuscripcion::query()
                        ->where('cliente_id',         $clienteId)
                        ->where('servicio_id',        $servicioId)
                        ->where('es_tarifa_principal', true)
                        ->whereIn('estado', [
                            ClienteSuscripcionEstadoEnum::ACTIVA->value,
                            ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION->value,
                        ])
                        ->exists();

                    if ($existe) {
                        // 3) Notificamos al usuario y detenemos la creación
                        Notification::make()
                            ->warning()
                            ->title('Servicio ya contratado')
                            ->body("El cliente ya tiene activa o pendiente la suscripción «{$servicio->nombre}».")
                            ->actions([
                                NotifyAction::make('ok')
                                    ->label('Entendido')
                                    ->button(),
                            ])
                            ->send();

                        $this->halt();
                    }
                }
            }
        }
    }

 protected function afterCreate(): void
    {
        if ($this->record) {
            $this->record->updateTotal();
            $this->record->loadMissing('items.servicio');
            $this->record->crearSuscripcionesDesdeItems();

             // 2) Mostrar toast si hay proyectos pendientes para este cliente
        $hayPendientes = Proyecto::query()
            ->where('cliente_id', $this->record->cliente_id)
            ->where('estado', '!=', ProyectoEstadoEnum::Finalizado->value)
            ->exists();

        if ($hayPendientes) {
            Notification::make()
                ->warning()
                ->title('Suscripciones pendientes')
                ->body('No se han creado nuevas suscripciones porque existen proyectos pendientes.')
                ->send();
        }



            // 💡 Aquí añadimos la lógica de actualizar el estado del Lead
            if ($this->record->lead_id && $this->record->lead) {
                $this->record->lead->update([
                    'estado' => \App\Enums\LeadEstadoEnum::CONVERTIDO, // o CONVERTIDO_PUNTUAL según lógica
                ]);
            }
        }
    }

   

}
