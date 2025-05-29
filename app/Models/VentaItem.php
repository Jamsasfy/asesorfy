<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class VentaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'venta_id',
        'servicio_id',
        'cantidad',
        'precio_unitario',
        'subtotal', // Lo calcularemos automáticamente, pero debe ser fillable
        'observaciones_item',
        'fecha_inicio_servicio', // Campo opcional para recurrentes
    ];

    protected $casts = [
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'fecha_inicio_servicio' => 'date', // O 'datetime' si usas hora
    ];

    // Relación muchos-a-uno con Venta (la venta padre)
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    // Relación muchos-a-uno con Servicio (el servicio vendido)
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    // --- Accessor/Mutator para calcular el subtotal automáticamente ---
    // Calculamos el subtotal al obtenerlo y al guardarlo
    protected function subtotal(): Attribute
    {
        return Attribute::make(
            // Accesor: cuando lees $ventaItem->subtotal
            get: fn (mixed $value, array $attributes) => $attributes['cantidad'] * $attributes['precio_unitario'],
            // Mutator: cuando guardas (opcional, puedes calcularlo en el observer/hook)
            // set: fn (mixed $value, array $attributes) => $attributes['cantidad'] * $attributes['precio_unitario'], // Esto es redundante si usas saving/created hook
        );
    }


    // --- Hooks para actualizar el importe_total de la Venta padre ---
    // Esto usa Eloquent Events. Alternativamente, Filament Repeater tiene sus propios hooks.
    // Empecemos con los de Eloquent Model si no usas los de Repeater directamente.
    protected static function booted(): void
    {
        // Cuando se crea, actualiza o elimina un VentaItem, actualiza el total de la Venta padre
        static::created(function (VentaItem $ventaItem) {
            $ventaItem->venta->updateTotal(); // Llamamos a un método en el modelo Venta
        });

        static::updated(function (VentaItem $ventaItem) {
             // Solo actualizar si cantidad o precio_unitario cambiaron (y por lo tanto, subtotal)
             if ($ventaItem->isDirty(['cantidad', 'precio_unitario'])) {
                 $ventaItem->venta->updateTotal();
             }
        });

        static::deleted(function (VentaItem $ventaItem) {
            $ventaItem->venta->updateTotal(); // Actualiza el total al eliminar un item
        });

         // Al recuperar, calcular el subtotal si no lo almacenamos (lo estamos almacenando, así que el accessor de get() es suficiente)
         // static::retrieved(function (VentaItem $ventaItem) {
         //     $ventaItem->subtotal = $ventaItem->cantidad * $ventaItem->precio_unitario;
         // });
    }
}