<?php

namespace App\Filament\Resources\RetencionIrpfResource\Pages;

use App\Filament\Resources\RetencionIrpfResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRetencionIrpf extends CreateRecord
{
    protected static string $resource = RetencionIrpfResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
