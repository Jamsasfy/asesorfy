<?php

namespace App\Filament\Resources\RetencionIrpfResource\Pages;

use App\Filament\Resources\RetencionIrpfResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRetencionIrpfs extends ListRecords
{
    protected static string $resource = RetencionIrpfResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
