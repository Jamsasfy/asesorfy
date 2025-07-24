<?php

namespace App\Models;

use App\Enums\TipoCuentaContable;
use Illuminate\Database\Eloquent\Model;

class CuentaCatalogo extends Model
{
    protected $table = 'cuentas_catalogo';

    protected $fillable = [
        'codigo',
        'descripcion',
        'grupo',
        'subgrupo',
        'nivel',
        'origen',
        'tipo',
        'es_activa',
    ];

    protected $casts = [
        'tipo' => TipoCuentaContable::class,
        'es_activa' => 'boolean',
    ];
}
