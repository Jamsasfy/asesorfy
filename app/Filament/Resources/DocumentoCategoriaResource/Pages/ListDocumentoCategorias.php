<?php

namespace App\Filament\Resources\DocumentoCategoriaResource\Pages;

use App\Filament\Resources\DocumentoCategoriaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentoCategorias extends ListRecords
{
    protected static string $resource = DocumentoCategoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
