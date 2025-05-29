<?php

namespace App\Filament\Resources\DocumentoCategoriaResource\Pages;

use App\Filament\Resources\DocumentoCategoriaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentoCategoria extends CreateRecord
{
    protected static string $resource = DocumentoCategoriaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    
}
