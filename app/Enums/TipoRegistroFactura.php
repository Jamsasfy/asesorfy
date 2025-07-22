<?php

// app/Enums/TipoRegistroFactura.php
namespace App\Enums;

enum TipoRegistroFactura: string
{
    case EMITIDA = 'emitida';
    case RECIBIDA = 'recibida';
}