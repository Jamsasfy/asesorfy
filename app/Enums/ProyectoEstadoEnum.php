<?php

namespace App\Enums;

enum ProyectoEstadoEnum: string
{
    case Pendiente = 'pendiente';
    case EnProgreso = 'en_progreso';
    case Finalizado = 'finalizado';
    case Cancelado = 'cancelado';
    // Añade más estados según tu flujo de trabajo
}