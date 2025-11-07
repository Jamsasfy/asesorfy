<?php

namespace App\Filament\Resources\SerieFacturaProveedorResource\Pages;

use App\Filament\Resources\SerieFacturaProveedorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSerieFacturaProveedor extends EditRecord
{
    protected static string $resource = SerieFacturaProveedorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
