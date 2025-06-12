<?php

namespace App\Filament\Resources\ProyectoResource\Pages;

use App\Filament\Resources\ProyectoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProyecto extends ViewRecord
{
    protected static string $resource = ProyectoResource::class;

     // Opcional: Si quieres añadir acciones específicas a la cabecera de la página de vista
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(), // Permite editar el proyecto desde la vista
        ];
    }

    
}
