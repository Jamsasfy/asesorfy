<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;


class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    

    protected function mutateFormDataBeforeCreate(array $data): array
{
    $creator = Auth::user(); // Obtenemos el objeto User del creador
    $data['creado_id'] = $creator->id; // Asignamos siempre el creador

    // --- Lógica para asignado_id ---
    // Verificamos si NO se seleccionó un asignado en el formulario Y si el CREADOR tiene el rol 'comercial'
    // ¡¡Asegúrate que el nombre del rol 'comercial' es exacto!!
    if (empty($data['asignado_id']) && $creator?->hasRole('comercial')) {
        // Si ambas condiciones son ciertas, auto-asigna el lead al creador
        $data['asignado_id'] = $creator->id;
    }
    // Si el creador NO es comercial, o si SÍ se seleccionó a alguien en el formulario,
    // $data['asignado_id'] mantendrá el valor del formulario (que podría ser null si no se seleccionó nada)

    return $data;
}



}
