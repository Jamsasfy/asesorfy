<?php

namespace App\Enums;

enum CicloFacturacionEnum: string
{
    case MENSUAL = 'mensual';
    case TRIMESTRAL = 'trimestral';
    case ANUAL = 'anual';

    public function label(): string
    {
        return match ($this) {
            self::MENSUAL => 'Mensual',
            self::TRIMESTRAL => 'Trimestral',
            self::ANUAL => 'Anual',
        };
    }
}
