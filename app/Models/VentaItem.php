<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute; // Mantener si lo usas en otros accesors
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    // Un VentaItem puede tener un Proyecto asociado
public function proyecto(): HasOne
{
    return $this->hasOne(Proyecto::class);
}

// Un VentaItem puede tener una Suscripción asociada
public function suscripcion(): HasOne
{
    // La relación es a través de la venta y el servicio, es un poco más compleja
    return $this->hasOne(ClienteSuscripcion::class, 'venta_origen_id', 'venta_id')
                ->where('servicio_id', $this->servicio_id);
}


   protected static function booted(): void
    {
        // <<< CAMBIO CLAVE AQUI: Integración del hook 'saving'
       static::saving(function (VentaItem $ventaItem) {
                    // Limpiar campos de descuento si no hay tipo definido
                    if (empty($ventaItem->descuento_tipo)) {
                        $ventaItem->descuento_valor = null;
                        $ventaItem->descuento_duracion_meses = null;
                        $ventaItem->descuento_valido_hasta = null;
                        $ventaItem->observaciones_descuento = null;
                    }

                    // Limpiar campos vacíos si son string vacíos
                    foreach (['descuento_valor', 'descuento_duracion_meses', 'descuento_valido_hasta', 'observaciones_descuento'] as $campo) {
                        if ($ventaItem->{$campo} === '') {
                            $ventaItem->{$campo} = null;
                        }
                    }

                    // 🧠 Cálculo de precio aplicado y subtotal
                    if (!is_null($ventaItem->precio_unitario)) {
                        $cantidad = (float)($ventaItem->cantidad ?? 1);
                        $precioUnitario = (float)$ventaItem->precio_unitario;
                        $subtotal = $cantidad * $precioUnitario;
                        $precioFinal = $subtotal;

                        if (!empty($ventaItem->descuento_tipo) && is_numeric($ventaItem->descuento_valor)) {
                            switch ($ventaItem->descuento_tipo) {
                                case 'porcentaje':
                                    $precioFinal = $subtotal - ($subtotal * ($ventaItem->descuento_valor / 100));
                                    break;
                                case 'fijo':
                                    $precioFinal = $subtotal - $ventaItem->descuento_valor;
                                    break;
                                case 'precio_final':
                                    $precioFinal = $ventaItem->descuento_valor;
                                    break;
                            }
                        }

                        // Aplicar límites
                        $precioFinal = max(0, $precioFinal);
                        $ventaItem->precio_unitario_aplicado = round($precioFinal / max(1, $cantidad), 4);
                        $ventaItem->subtotal_aplicado = round($precioFinal, 2);
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