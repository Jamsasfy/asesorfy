<?php

namespace App\Filament\Resources\TipoIvaResource\Pages;

use App\Filament\Resources\TipoIvaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoIva extends EditRecord
{
    protected static string $resource = TipoIvaResource::class;

     protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
