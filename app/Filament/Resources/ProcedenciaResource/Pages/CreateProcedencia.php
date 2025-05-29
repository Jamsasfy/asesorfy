<?php

namespace App\Filament\Resources\ProcedenciaResource\Pages;

use App\Filament\Resources\ProcedenciaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProcedencia extends CreateRecord
{
    protected static string $resource = ProcedenciaResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
