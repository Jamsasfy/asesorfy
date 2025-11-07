<?php

namespace App\Enums;

enum EstadoRegistroFactura: string
{
    case PENDIENTE = 'pendiente';
    case ACEPTADA = 'aceptada';
    case RECHAZADA = 'rechazada';
}