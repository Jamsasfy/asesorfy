<?php

namespace App\Enums;

enum ClienteEstadoEnum: string
{
    case PENDIENTE = 'pendiente';
    case PENDIENTE_ASIGNACION = 'pendiente_asignacion';
    case ACTIVO = 'activo';
    case IMPAGADO = 'impagado';
    case BLOQUEADO = 'bloqueado';
    case RESCINDIDO = 'rescindido';
    case BAJA = 'baja';
    case REQUIERE_ATENCION = 'requiere_atencion';
}