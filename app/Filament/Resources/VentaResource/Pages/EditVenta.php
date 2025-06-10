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
 // Se ejecuta después de que el formulario se ha llenado con los datos del registro existente
    protected function afterFill(): void
    {
        // Solo recalculamos si los totales vienen vacíos en el modelo
        if (blank($this->record->importe_total)) {
            $this->record->updateTotal();
            // Refrescamos el formulario con los nuevos valores
            $this->form->fill([
                'importe_total'          => $this->record->importe_total,
                'importe_total_sin_iva'  => $this->record->importe_total_sin_iva,
            ]);
        }
    }

    protected function afterSave(): void
    {
        $this->record?->updateTotal();
    }

    

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
