<?php

namespace App\Filament\Resources\CuentaClienteResource\Pages;

use App\Filament\Resources\CuentaClienteResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCuentaCliente extends CreateRecord
{
    protected static string $resource = CuentaClienteResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    

 protected function getCreatedNotification(): ?Notification
    {
         $clienteNombre = $this->record->cliente?->razon_social ?? 'Cliente';
        return Notification::make()
            ->title("Cuenta Contable del cliente {$clienteNombre} creada correctamente")
            ->body('Ya se puede usar en las facturas.')
            ->success()
            ->icon('icon-serviciosproductosusuario')
            ->persistent();
    }
    

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['codigo'] = $data['codigo_prefijo'] . $data['codigo_sufijo'];
        unset($data['codigo_prefijo'], $data['codigo_sufijo']);
        return $data;
    }


}
