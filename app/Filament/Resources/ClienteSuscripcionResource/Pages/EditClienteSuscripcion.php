<?php

namespace App\Filament\Resources\ClienteSuscripcionResource\Pages;

use App\Filament\Resources\ClienteSuscripcionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClienteSuscripcion extends EditRecord
{
    protected static string $resource = ClienteSuscripcionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
