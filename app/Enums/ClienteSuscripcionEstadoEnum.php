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



      // ▼▼▼ PÉGALO AQUÍ DENTRO ▼▼▼
    public function getLabel(): string
    {
        return match ($this) {
            self::PENDIENTE_ACTIVACION => 'Pendiente de Activación',
            self::ACTIVA => 'Activa',
            self::EN_PRUEBA => 'En Prueba',
            self::IMPAGADA => 'Impagada',
            self::PENDIENTE_CANCELACION => 'Pendiente de Cancelación',
            self::CANCELADA => 'Cancelada',
            self::FINALIZADA => 'Finalizada',
            self::REEMPLAZADA => 'Reemplazada',
            self::PAUSADA => 'Pausada',
        };
    }

    
}

 
