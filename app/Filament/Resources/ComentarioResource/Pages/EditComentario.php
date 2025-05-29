<?php

namespace App\Filament\Resources\ComentarioResource\Pages;

use App\Filament\Resources\ComentarioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditComentario extends EditRecord
{
    protected static string $resource = ComentarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
