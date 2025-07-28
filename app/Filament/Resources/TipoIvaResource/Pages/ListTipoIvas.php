<?php

namespace App\Filament\Resources\TipoIvaResource\Pages;

use App\Filament\Resources\TipoIvaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipoIvas extends ListRecords
{
    protected static string $resource = TipoIvaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
