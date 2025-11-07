<?php

namespace App\Filament\Resources\CuentaCatalogoResource\Pages;

use App\Filament\Resources\CuentaCatalogoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCuentaCatalogos extends ListRecords
{
    protected static string $resource = CuentaCatalogoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
