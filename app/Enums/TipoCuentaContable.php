<?php

namespace App\Enums;

enum TipoCuentaContable: string
{
    case GASTO = 'gasto';
    case INGRESO = 'ingreso';
    case FINANCIERO = 'financiero';
    case OTRO = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::GASTO => 'Gasto',
            self::INGRESO => 'Ingreso',
            self::FINANCIERO => 'Financiero',
            self::OTRO => 'Otro',
        };
    }
}
