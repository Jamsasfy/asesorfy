<?php

namespace App\Filament\Resources\DocumentoResource\Pages;

use App\Filament\Resources\DocumentoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ViewDocumento extends ViewRecord
{
    protected static string $resource = DocumentoResource::class;

    public function getTitle(): string
    {
        return '📄 Documento: ' . ($this->record->nombre ?? 'Sin nombre');
    }

    public function getHeading(): string
    {
        return '📄 Documento: ' . ($this->record->nombre ?? 'Sin nombre');
    }

    public function getSubheading(): string
{
    return 'Cliente: ' . ($this->record->cliente->razon_social ?? 'Desconocido');
}



   protected function getHeaderActions(): array
{
    return [
        EditAction::make()->label('Modificar documento') // Texto del botón
        ->icon('heroicon-o-pencil-square') // Icono (Heroicon válido)
        ->color('warning') // Color amarillo
        ->tooltip('Editar la información de este documento'),
        
        DeleteAction::make()
        ->requiresConfirmation()
        ->modalHeading('¿Estás seguro de que quieres eliminar este documento?'),

      // 🆕 Botón para volver al listado
      \Filament\Actions\Action::make('volver')
      ->label('Volver al listado')
      ->icon('heroicon-o-arrow-left')
      ->url(fn () => route('filament.admin.resources.documentos.index'))
      ->visible(fn () => auth()->user()?->hasRole('super_admin'))
      ->color('gray'),


    ];
}


}
