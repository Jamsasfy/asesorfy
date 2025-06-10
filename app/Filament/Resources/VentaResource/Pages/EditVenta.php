<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Filament\Resources\VentaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Get; // Importar la clase Get
use Filament\Forms\Set;

class EditVenta extends EditRecord
{
    protected static string $resource = VentaResource::class;

   
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    

 // Tu método afterSave() que recalcula el total y lo guarda en la DB después de actualizar
    protected function afterSave(): void
    {
        if ($this->record) {
            $this->record->updateTotal(); // Asumiendo que este método existe en tu modelo Venta
        }
    }


   

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
