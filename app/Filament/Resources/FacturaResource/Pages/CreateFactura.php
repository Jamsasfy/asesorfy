<?php

namespace App\Filament\Resources\FacturaResource\Pages;

use App\Filament\Resources\FacturaResource;
use Filament\Actions\Action;
use Filament\Forms\Get;
use Filament\Resources\Pages\CreateRecord;

class CreateFactura extends CreateRecord
{
    protected static string $resource = FacturaResource::class;

    protected function getFormActions(): array
    {
        return [
            Action::make('submit')
                ->label('Guardar')
                ->visible(fn (Get $get) => $get('mostrar_guardar') === true),
        ];
    }
}
