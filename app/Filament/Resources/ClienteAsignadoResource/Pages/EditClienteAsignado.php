<?php

namespace App\Filament\Resources\ClienteAsignadoResource\Pages;

use App\Filament\Resources\ClienteAsignadoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClienteAsignado extends EditRecord
{
    protected static string $resource = ClienteAsignadoResource::class;

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
