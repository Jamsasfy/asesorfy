<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TipoIva extends Model
{
    use HasFactory;

    protected $table = 'tipos_iva';

    protected $fillable = [
        'porcentaje',
        'recargo_equivalencia',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:2',
        'recargo_equivalencia' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function getEtiquetaAttribute(): string
    {
        if ($this->recargo_equivalencia > 0) {
            return "{$this->porcentaje} + {$this->recargo_equivalencia}";
        }
        return (string) $this->porcentaje;
    }
}
