<?php

namespace App\Filament\Resources\DocumentoSubtipoResource\Pages;

use App\Filament\Resources\DocumentoSubtipoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentoSubtipos extends ListRecords
{
    protected static string $resource = DocumentoSubtipoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
