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
   
 

 protected function afterCreate(): void
{
    // Aseguramos que el registro de la venta se ha creado correctamente
    if ($this->record) {

        // 1. (LA LÍNEA CLAVE QUE FALTABA)
        // Llama al método en el modelo Venta que crea los Proyectos y Suscripciones.
        $this->record->processSaleAfterCreation();

        // 2. Actualiza el importe_total en el modelo Venta.
        // Se ejecuta después del paso 1 para asegurar que todo está creado.
        $this->record->updateTotal();

        // 3. Muestra una notificación SI esta venta en particular generó proyectos.
        if ($this->record->proyectos()->exists()) {
            Notification::make()
                ->warning()
                ->title('Proyectos y Suscripciones Creadas')
                ->body('Se han generado proyectos de activación. Algunas suscripciones pueden estar pendientes hasta que se completen.')
                ->send();
        }

        // 4. Actualiza el estado del Lead asociado a esta venta.
        if ($this->record->lead_id && $this->record->lead) {
            $this->record->lead->update([
                'estado' => \App\Enums\LeadEstadoEnum::CONVERTIDO,
            ]);
        }
    }
}
   

}
