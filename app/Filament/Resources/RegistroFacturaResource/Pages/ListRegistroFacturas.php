<?php

namespace App\Filament\Resources\RegistroFacturaResource\Pages;

use App\Filament\Resources\RegistroFacturaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegistroFacturas extends ListRecords
{
    protected static string $resource = RegistroFacturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
