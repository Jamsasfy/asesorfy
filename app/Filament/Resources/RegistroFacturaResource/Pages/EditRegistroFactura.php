<?php

namespace App\Filament\Resources\RegistroFacturaResource\Pages;

use App\Filament\Resources\RegistroFacturaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRegistroFactura extends EditRecord
{
    protected static string $resource = RegistroFacturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
