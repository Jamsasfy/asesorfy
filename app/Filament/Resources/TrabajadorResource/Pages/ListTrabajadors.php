<?php

namespace App\Filament\Resources\TrabajadorResource\Pages;

use App\Filament\Resources\TrabajadorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTrabajadors extends ListRecords
{
    protected static string $resource = TrabajadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
