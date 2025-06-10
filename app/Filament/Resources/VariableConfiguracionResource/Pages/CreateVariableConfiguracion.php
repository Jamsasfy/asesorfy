<?php

namespace App\Filament\Resources\VariableConfiguracionResource\Pages;

use App\Filament\Resources\VariableConfiguracionResource;
use App\Models\VariableConfiguracion;
use App\Services\ConfiguracionService; // ¡IMPORTA TU SERVICIO AQUÍ!
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model; // <--- Importa el Model de Eloquent


class CreateVariableConfiguracion extends CreateRecord
{
    protected static string $resource = VariableConfiguracionResource::class;

    // Sobreescribe el método para manejar la creación del registro
    protected function handleRecordCreation(array $data): Model
    {
        // Usa tu ConfiguracionService para guardar la variable.
        // Este servicio ya sabe cómo cifrar si 'es_secreto' es verdadero.
        $variable = ConfiguracionService::set(
            $data['nombre_variable'],
            $data['valor_variable'],
            $data['tipo_dato'],
            $data['descripcion'],
            $data['es_secreto']
        );

        return $variable; // Asegura que se devuelve una instancia de Model
    }

    // Opcional: Define a dónde redirigir después de crear el registro
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index'); // Redirige a la lista de variables
    }
}