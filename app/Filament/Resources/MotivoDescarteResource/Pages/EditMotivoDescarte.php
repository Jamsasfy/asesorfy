<?php

namespace App\Filament\Resources\MotivoDescarteResource\Pages;

use App\Filament\Resources\MotivoDescarteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMotivoDescarte extends EditRecord
{
    protected static string $resource = MotivoDescarteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
