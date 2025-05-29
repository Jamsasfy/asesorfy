<?php

namespace App\Filament\Resources\ClienteAsignadoResource\Pages;

use App\Filament\Resources\ClienteAsignadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClienteAsignados extends ListRecords
{
    protected static string $resource = ClienteAsignadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
