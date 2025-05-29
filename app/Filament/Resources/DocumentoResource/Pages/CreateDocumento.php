<?php

namespace App\Filament\Resources\DocumentoResource\Pages;

use App\Filament\Resources\DocumentoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CreateDocumento extends CreateRecord
{
    protected static string $resource = DocumentoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        
        $user = Auth::user();
        $data['user_id'] = $user->id;
        
        // Obtener el tipo MIME y el nombre del archivo
        $data['mime_type'] = Storage::disk('public')->mimeType($data['ruta']);
      //  $data['nombre'] = basename($data['ruta']);

    
        // Comprobar si el usuario está asociado a un trabajador
        if ($user->trabajador) {  // Esto asegura que el usuario tiene un trabajador asociado
            $data['verificado'] = true;  // Si es trabajador, el documento será verificado
        } else {
            $data['verificado'] = false;  // Si no es trabajador, no será verificado
        }
    
      
    
        return $data;
    }


}
