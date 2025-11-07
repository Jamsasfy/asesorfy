<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoServicioBase extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'cuenta_catalogo_id',
    ];

    public function cuentaCatalogo(): BelongsTo
    {
        return $this->belongsTo(CuentaCatalogo::class);
    }
}
