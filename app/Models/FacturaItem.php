<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'factura_id', 'servicio_id', 'descripcion', 'cantidad', 'cliente_suscripcion_id', 'precio_unitario_aplicado', 
        'precio_unitario', 'porcentaje_iva', 'subtotal', 'importe_descuento', 
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'precio_unitario_aplicado' => 'decimal:2', 
        'porcentaje_iva' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Cada línea pertenece a una factura
    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }
     public function clienteSuscripcion(): BelongsTo
    {
        return $this->belongsTo(ClienteSuscripcion::class, 'cliente_suscripcion_id');
    }
     // Un ítem de factura pertenece a un Servicio
    public function servicio(): BelongsTo // <-- ¡Añadir esta función!
    {
        return $this->belongsTo(Servicio::class); // Asegúrate de que App\Models\Servicio existe
    }
}