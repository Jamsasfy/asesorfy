<?php

namespace App\Enums;

enum VentaCorreccionEstadoEnum: string
{
    case SOLICITADA = 'solicitada';
    case EN_PROCESO = 'en_proceso';
    case COMPLETADA = 'completada';
    case RECHAZADA = 'rechazada';
}