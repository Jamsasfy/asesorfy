<?php

namespace App\Filament\Resources\ProductoServicioClienteResource\Pages;

use App\Filament\Resources\ProductoServicioClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductoServicioClientes extends ListRecords
{
    protected static string $resource = ProductoServicioClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
