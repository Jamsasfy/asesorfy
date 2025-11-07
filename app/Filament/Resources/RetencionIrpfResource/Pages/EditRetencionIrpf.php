<?php

namespace App\Filament\Resources\RetencionIrpfResource\Pages;

use App\Filament\Resources\RetencionIrpfResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRetencionIrpf extends EditRecord
{
    protected static string $resource = RetencionIrpfResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
