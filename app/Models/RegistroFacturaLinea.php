<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroFacturaLinea extends Model
{
    use HasFactory;

    protected $table = 'registro_factura_lineas';

    protected $fillable = [
        'registro_factura_id',
        'servicio_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'porcentaje_iva',
        'descuento_tipo',
        'descuento_valor',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'porcentaje_iva' => 'decimal:2',
        'descuento_valor' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Una línea pertenece a un RegistroFactura.
     */
    public function registroFactura(): BelongsTo
    {
        return $this->belongsTo(RegistroFactura::class);
    }

    /**
     * Una línea puede estar asociada a un Servicio del catálogo.
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}