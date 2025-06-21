<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Filament\Resources\VentaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

use App\Models\Venta;

class CreateVenta extends CreateRecord
{
    protected static string $resource = VentaResource::class;

   
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
   

 protected function afterCreate(): void
{
    if ($this->record) {
        $this->record->updateTotal();

        // Precargamos los servicios para cada item antes de crear suscripciones
        $this->record->loadMissing('items.servicio');

        $this->record->crearSuscripcionesDesdeItems();
    }
}

   

}
