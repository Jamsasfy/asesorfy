<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'factura_id', 'servicio_id', 'descripcion', 'cantidad',
        'precio_unitario', 'porcentaje_iva', 'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'porcentaje_iva' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Cada línea pertenece a una factura
    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }
}