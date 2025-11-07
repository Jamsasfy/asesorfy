<?php

namespace App\Enums;

enum TipoNifProveedor: string
{
    case NACIONAL = 'nacional';
    case INTRACOMUNITARIO = 'intracomunitario';
    case OTRO = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::NACIONAL => 'Nacional',
            self::INTRACOMUNITARIO => 'Intracomunitario',
            self::OTRO => 'Otro',
        };
    }
}