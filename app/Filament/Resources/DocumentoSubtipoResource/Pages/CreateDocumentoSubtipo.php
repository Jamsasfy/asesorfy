<?php

namespace App\Filament\Resources\DocumentoSubtipoResource\Pages;

use App\Filament\Resources\DocumentoSubtipoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentoSubtipo extends CreateRecord
{
    protected static string $resource = DocumentoSubtipoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    
}
