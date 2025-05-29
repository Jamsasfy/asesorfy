<?php

namespace App\Filament\Resources\ClienteAsignadoResource\Pages;

use App\Filament\Resources\ClienteAsignadoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateClienteAsignado extends CreateRecord
{
    protected static string $resource = ClienteAsignadoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    
}
