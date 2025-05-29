<?php

namespace App\Filament\Resources\DocumentoSubtipoResource\Pages;

use App\Filament\Resources\DocumentoSubtipoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocumentoSubtipo extends EditRecord
{
    protected static string $resource = DocumentoSubtipoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    
}
