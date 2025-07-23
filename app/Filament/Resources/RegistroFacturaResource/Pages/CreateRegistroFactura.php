<?php

namespace App\Filament\Resources\RegistroFacturaResource\Pages;

use App\Filament\Resources\RegistroFacturaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile; // <-- AÑADE ESTE 'USE'

class CreateRegistroFactura extends CreateRecord
{
    protected static string $resource = RegistroFacturaResource::class;

    public ?array $justificante_path = []; // <-- AÑADE ESTA PROPIEDAD
}