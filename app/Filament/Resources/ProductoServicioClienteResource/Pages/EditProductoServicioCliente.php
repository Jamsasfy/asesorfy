<?php

namespace App\Filament\Resources\ProductoServicioClienteResource\Pages;

use App\Filament\Resources\ProductoServicioClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductoServicioCliente extends EditRecord
{
    protected static string $resource = ProductoServicioClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
