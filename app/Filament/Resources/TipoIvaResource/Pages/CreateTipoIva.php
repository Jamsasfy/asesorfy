<?php

namespace App\Filament\Resources\TipoIvaResource\Pages;

use App\Filament\Resources\TipoIvaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTipoIva extends CreateRecord
{
    protected static string $resource = TipoIvaResource::class;

     protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    
}
