<?php

namespace App\Enums;

enum ServicioTipoEnum: string
{
    case UNICO = 'unico';
    case RECURRENTE = 'recurrente';

    // Opcional: para obtener una etiqueta legible
    public function getLabel(): string
    {
        return match ($this) {
            self::UNICO => 'Único',
            self::RECURRENTE => 'Recurrente',
        };
    }
}