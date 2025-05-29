<?php

namespace App\Filament\Resources\OficinaResource\Pages;

use App\Filament\Resources\OficinaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOficina extends EditRecord
{
    protected static string $resource = OficinaResource::class;
    
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
