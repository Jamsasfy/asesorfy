<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContadorFactura extends Model
{
    use HasFactory;

    protected $table = 'contadores_facturas';

    protected $fillable = [
        'serie', 'anio', 'ultimo_numero',
    ];
}