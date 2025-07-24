<?php

namespace App\Enums;

enum EstadoPago: string
{
    case PENDIENTE = 'pendiente';
    case PAGADO = 'pagado';
    case ABONADO = 'abonado';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente de pago',
            self::PAGADO => 'Pagado',
            self::ABONADO => 'Abonado o devuelto',
        };
    }
}
