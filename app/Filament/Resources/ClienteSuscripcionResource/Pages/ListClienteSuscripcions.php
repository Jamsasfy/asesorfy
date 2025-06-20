<?php

namespace App\Filament\Resources\ClienteSuscripcionResource\Pages;

use App\Filament\Resources\ClienteSuscripcionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClienteSuscripcions extends ListRecords
{
    protected static string $resource = ClienteSuscripcionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
