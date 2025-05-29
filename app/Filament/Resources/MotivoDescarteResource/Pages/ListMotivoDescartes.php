<?php

namespace App\Filament\Resources\MotivoDescarteResource\Pages;

use App\Filament\Resources\MotivoDescarteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMotivoDescartes extends ListRecords
{
    protected static string $resource = MotivoDescarteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
