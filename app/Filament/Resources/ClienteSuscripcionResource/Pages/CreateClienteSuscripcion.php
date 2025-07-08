<?php

namespace App\Filament\Resources\ClienteSuscripcionResource\Pages;

use App\Filament\Resources\ClienteSuscripcionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateClienteSuscripcion extends CreateRecord
{
    protected static string $resource = ClienteSuscripcionResource::class;

      protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
