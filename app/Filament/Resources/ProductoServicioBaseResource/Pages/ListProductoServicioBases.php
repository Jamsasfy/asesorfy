<?php

namespace App\Filament\Resources\ProductoServicioBaseResource\Pages;

use App\Filament\Resources\ProductoServicioBaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductoServicioBases extends ListRecords
{
    protected static string $resource = ProductoServicioBaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
