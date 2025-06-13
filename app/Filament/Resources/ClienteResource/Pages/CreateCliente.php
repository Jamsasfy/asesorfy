<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use App\Filament\Resources\VentaResource;
use App\Models\Cliente;
use App\Models\Lead;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Model;
use Filament\Pages\Actions\CreateAction;
use Filament\Pages\Actions\CancelAction;
use Filament\Forms\Components\Hidden;



class CreateCliente extends CreateRecord
{
    protected static string $resource = ClienteResource::class;

    protected function afterCreate(): void
    {
       // parent::afterCreate();

        $leadId = request()->query('lead_id');
        if ($leadId) {
            Lead::where('id', $leadId)
                ->update(['cliente_id' => $this->record->getKey()]);
        }
    }



    

    public function mount(): void
    {
        // Si venimos con lead_id y ya existe Cliente, saltamos directamente a Venta
        $leadId = request()->query('lead_id');
        if ($leadId) {
            $existing = Cliente::where('lead_id', $leadId)->first();
            if ($existing) {
                // Notificamos de forma opcional
                Notification::make()
                    ->body('Este lead ya se convirtió a cliente. Vamos a crear la venta.')
                    ->info()
                    ->send();

                // Redirigimos al formulario de Venta
                $this->redirect(
                    VentaResource::getUrl('create', [
                        'cliente_id' => $existing->getKey(),
                        'lead_id'    => $leadId,
                    ])
                );

                return; // Termina el mount para no renderizar el formulario
            }
        }

        parent::mount();
    }


     protected array $leadParams = [];

   /*   protected function getRedirectUrl(): string
    {
        // Lógica previa para cuando creamos un cliente nuevo...
        if (request()->query('next') === 'sale' && request()->has('lead_id')) {
            return VentaResource::getUrl('create', [
                'cliente_id' => $this->record->getKey(),
                'lead_id'    => request()->query('lead_id'),
            ]);
        }

        // Si no, a la vista de Cliente
        return static::getResource()::getUrl('view', [
            'record' => $this->record->getKey(),
        ]);
    }
 */

   /**
     * 2) Decide adónde ir tras crear:
     *    - Si ya existe Cliente para este Lead, va a Crear Venta
     *    - Si es la primera vez con next=sale, va a Crear Venta
     *    - Si no, a la vista de Cliente
     */
    protected function getRedirectUrl(): string
    {
        $leadId = request()->query('lead_id');
        $next   = request()->query('next');

        // Caso A: ya vinculamos cliente en afterCreate()
        if ($leadId && $existing = Cliente::where('lead_id', $leadId)->first()) {
            return VentaResource::getUrl('create', [
                'cliente_id' => $existing->getKey(),
                'lead_id'    => $leadId,
            ]);
        }

        // Caso B: primera creación, next=sale
        if ($next === 'sale' && $leadId) {
            return VentaResource::getUrl('create', [
                'cliente_id' => $this->record->getKey(),
                'lead_id'    => $leadId,
            ]);
        }

        // Caso C: normal, vista de Cliente
        return static::getResource()::getUrl('view', [
            'record' => $this->record->getKey(),
        ]);
    }

   
    protected function handleRecordCreation(array $data): Model
    {
        // Tu validación y autocompletado de razon_social…
        if (
            empty($data['razon_social']) &&
            (empty($data['nombre']) || empty($data['apellidos']))
        ) {
            Notification::make()
                ->title('❌ Falta información')
                ->body('Rellena razón social o nombre+apellidos.')
                ->danger()
                ->persistent()
                ->send();
            $this->halt();
            return new $this->getModel();
        }
        if (empty($data['razon_social'])) {
            $data['razon_social'] = trim("{$data['nombre']} {$data['apellidos']}");
        }
        return parent::handleRecordCreation($data);
    }

    
    protected function getCreatedNotification(): ?Notification
{
    return Notification::make()
        ->title('✅ Cliente creado correctamente')
        ->body('El cliente ha sido añadido al sistema. Recuerda revisar su documentación y estado.')
        ->success() // puedes cambiar por ->info(), ->warning(), ->danger()
        ->icon('icon-customer') // cualquier icono Heroicon
        ->persistent(); // no se cierra solo
}


}
