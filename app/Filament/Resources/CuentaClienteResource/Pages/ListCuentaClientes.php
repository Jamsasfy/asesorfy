<?php

namespace App\Filament\Resources\CuentaClienteResource\Pages;

use App\Filament\Resources\CuentaClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCuentaClientes extends ListRecords
{
    protected static string $resource = CuentaClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
