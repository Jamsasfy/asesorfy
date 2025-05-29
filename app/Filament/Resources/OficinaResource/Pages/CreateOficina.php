<?php

namespace App\Filament\Resources\OficinaResource\Pages;

use App\Filament\Resources\OficinaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateOficina extends CreateRecord
{
    protected static string $resource = OficinaResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
