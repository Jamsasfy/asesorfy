<?php

namespace App\Enums;

enum ClienteSuscripcionEstadoEnum: string
{
   case PENDIENTE_ACTIVACION = 'pendiente_activacion';
    case ACTIVA = 'activa';
    case EN_PRUEBA = 'en_prueba';
    case IMPAGADA = 'impagada';
    case PENDIENTE_CANCELACION = 'pendiente_cancelacion';
    case CANCELADA = 'cancelada';
    case FINALIZADA = 'finalizada';
    case REEMPLAZADA = 'reemplazada'; // Útil para cuando se hace un upgrade/downgrade
    case PAUSADA = 'pausada';
}
