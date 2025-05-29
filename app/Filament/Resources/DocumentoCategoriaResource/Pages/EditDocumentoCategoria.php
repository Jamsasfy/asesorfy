<?php

namespace App\Filament\Resources\DocumentoCategoriaResource\Pages;

use App\Filament\Resources\DocumentoCategoriaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocumentoCategoria extends EditRecord
{
    protected static string $resource = DocumentoCategoriaResource::class;

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
