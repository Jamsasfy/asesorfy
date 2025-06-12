<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel; // <<< Importar la interfaz HasLabel

enum ProyectoEstadoEnum: string implements HasLabel // <<< Implementar la interfaz
{
    case Pendiente = 'pendiente';
    case EnProgreso = 'en_progreso';
    case Finalizado = 'finalizado';
    case Cancelado = 'cancelado';
    // Añade más estados según tu flujo de trabajo, manteniendo el 'value' en snake_case
    // y el 'label' en formato legible para el usuario.

    /**
     * Devuelve la etiqueta legible para el usuario para cada estado del Enum.
     */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnProgreso => 'En Progreso',
            self::Finalizado => 'Finalizado',
            self::Cancelado => 'Cancelado',
            // Añade los demás casos aquí si tienes más estados
        };
    }

    /**
     * Opcional: Para el filtro de LeadResource, si necesitas saber si es un estado final.
     * Si no lo usas, puedes eliminar este método.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Finalizado, self::Cancelado]);
    }
}