<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute; // Mantener si lo usas en otros accesors

class VentaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'venta_id',
        'servicio_id',
        'cantidad',
        'precio_unitario',          // Precio base del servicio sin descuento, sin IVA
        'subtotal',                 // Subtotal base del servicio (cantidad * precio_unitario), sin descuento, sin IVA
        'subtotal_con_iva', // <<< NUEVO CAMPO AÑADIDO
        'observaciones_item',
        'fecha_inicio_servicio',    // Campo opcional para recurrentes
        'precio_unitario_aplicado', // NUEVO: Precio unitario FINAL aplicado a esta línea (con descuento si existe, sin IVA)
        'subtotal_aplicado',        // NUEVO: Subtotal FINAL de la línea (cantidad * precio_unitario_aplicado), sin IVA
        'descuento_tipo',
        'descuento_valor',
        'descuento_duracion_meses',
        'descuento_valido_hasta',
        'observaciones_descuento',  // Campo para la descripción del descuento
        'subtotal_aplicado_con_iva', // <<< NUEVO CAMPO AÑADIDO

    ];

    protected $casts = [
        'precio_unitario'          => 'decimal:4', // Mantenemos 4 decimales para mayor precisión
        'subtotal'                 => 'decimal:2', // Subtotal base (cantidad * precio_unitario)
        'subtotal_con_iva'         => 'decimal:2', // <<< NUEVO CAST AÑADIDO
        'fecha_inicio_servicio'    => 'date',     
        'precio_unitario_aplicado' => 'decimal:4',
        'subtotal_aplicado'        => 'decimal:2',
        'descuento_valor'          => 'decimal:2',
        'descuento_duracion_meses' => 'integer',
        'descuento_valido_hasta'   => 'date',
        'subtotal_aplicado_con_iva' => 'decimal:2',
        
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

    // --- REMOVIDO: Accessor/Mutator para 'subtotal' ---
    // Si 'subtotal' va a guardar el valor base (sin descuento),
    // y 'subtotal_aplicado' el valor con descuento, no necesitamos un accessor
    // para 'subtotal' que lo calcule automáticamente.
    // El valor de 'subtotal' se guardará desde el formulario de Filament.
    // protected function subtotal(): Attribute { ... }


   protected static function booted(): void
    {
        // <<< CAMBIO CLAVE AQUI: Integración del hook 'saving'
        static::saving(function (VentaItem $ventaItem) {
            // dd('VentaItem Saving Hook', ['descuento_tipo_before_check' => $ventaItem->descuento_tipo, 'descuento_valor_before_nullify' => $ventaItem->descuento_valor]); // DEBUGGING DD

            // Si el descuento_tipo está vacío (null o cadena vacía), limpiar todos los campos de descuento
            if (empty($ventaItem->descuento_tipo)) {
                $ventaItem->descuento_valor = null;
                $ventaItem->descuento_duracion_meses = null;
                $ventaItem->descuento_valido_hasta = null;
                $ventaItem->observaciones_descuento = null;
            }
            // Asegurarse de que si el valor numérico del descuento es 0 o null, se guarde como null
            // Esto es útil si el campo se vacía pero no se marca como 'sin descuento' explícitamente
            // Comprobamos si el valor no es nulo Y es 0 (para no nullificar un 0 válido)
            // O si es una cadena vacía
            if ($ventaItem->descuento_valor === '') {
                 $ventaItem->descuento_valor = null;
            }
            if ($ventaItem->descuento_duracion_meses === '') {
                 $ventaItem->descuento_duracion_meses = null;
            }
            if ($ventaItem->descuento_valido_hasta === '') {
                 $ventaItem->descuento_valido_hasta = null;
            }
            if ($ventaItem->observaciones_descuento === '') {
                 $ventaItem->observaciones_descuento = null;
            }
        });

        // Tus hooks existentes (CREATED, UPDATED, DELETED) - SIN CAMBIOS AQUI
        static::created(function (VentaItem $ventaItem) {
            $ventaItem->venta->updateTotal();
        });

        static::updated(function (VentaItem $ventaItem) {
            // Solo actualizar si alguno de los campos que afectan al total aplicado cambiaron
            if ($ventaItem->isDirty([
                'cantidad',
                'precio_unitario',
                'precio_unitario_aplicado', 
                'subtotal_aplicado', // Incluir este
                'subtotal_aplicado_con_iva', // Incluir este
                'descuento_tipo',
                'descuento_valor',
                'descuento_duracion_meses',
                'descuento_valido_hasta',
                'observaciones_descuento', // Incluir este
                'observaciones_item', // Incluir este
                'fecha_inicio_servicio', // Incluir este
            ])) {
                $ventaItem->venta->updateTotal();
            }
        });

        static::deleted(function (VentaItem $ventaItem) {
            $ventaItem->venta->updateTotal(); // Actualiza el total al eliminar un item
        });
    }
    
}