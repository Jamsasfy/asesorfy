<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RetencionIrpf extends Model
{
    use HasFactory;

    protected $table = 'retencion_irpfs';

    protected $fillable = [
        'porcentaje',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:2',
        'activo' => 'boolean',
    ];
}
