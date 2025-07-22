<?php

// app/Enums/MedioDePago.php
namespace App\Enums;

enum MedioDePago: string
{
    case TRANSFERENCIA = 'transferencia';
    case EFECTIVO = 'efectivo';
    case TARJETA = 'tarjeta';
    case DOMICILIACION = 'domiciliacion';
    case OTRO = 'otro';
}