<?php

namespace App\Filament\Resources\SerieFacturaProveedorResource\Pages;

use App\Filament\Resources\SerieFacturaProveedorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSerieFacturaProveedors extends ListRecords
{
    protected static string $resource = SerieFacturaProveedorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
