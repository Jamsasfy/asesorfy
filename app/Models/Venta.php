<?php

namespace App\Models;

use App\Enums\ServicioTipoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute; // Si usas accessors/mutators
use App\Models\Cliente;
use Carbon\Carbon;

class Venta extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'lead_id',
        'user_id', // El comercial
        'fecha_venta',
        'importe_total', // Lo calcularemos automáticamente, pero debe ser fillable
        'observaciones',
        // No incluimos 'tipo_venta' porque lo eliminamos
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'importe_total' => 'decimal:2', // Asegura que se maneja como decimal
    ];

    protected $appends = ['tipo_venta'];


    // Relación muchos-a-uno con Cliente
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    // Relación muchos-a-uno con Lead (nullable)
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    // Relación muchos-a-uno con User (el comercial), nombrada 'comercial'
    public function comercial(): BelongsTo // <-- Nombre del método cambiado a 'comercial'
    {
        return $this->belongsTo(User::class, 'user_id'); // <-- Sigue relacionándose con el modelo User
    }

    // Relación uno-a-muchos con VentaItems (los items de la venta)
    public function items(): HasMany
    {
        return $this->hasMany(VentaItem::class);
    }

    // Opcional: Accessor para obtener el tipo de venta (puntual, recurrente, mixta)
    // basado en los tipos de servicios de sus items.
    // Esto demuestra cómo obtener la información sin tener la columna 'tipo_venta'.
    protected function tipoVenta(): Attribute
    {
         return Attribute::make(
             get: function (mixed $value, array $attributes) {
                $itemTypes = $this->items->pluck('servicio.tipo')->unique();

                if ($itemTypes->contains('recurrente') && $itemTypes->contains('unico')) {
                    return 'mixta';
                } elseif ($itemTypes->contains('recurrente')) {
                    return 'recurrente';
                } elseif ($itemTypes->contains('unico')) {
                    return 'puntual';
                }
                return null; // O 'desconocido' si no hay items
             },
         );
    }

    
     // Método para recalcular y guardar el importe total
    public function updateTotal(): void
    {
        // <<< AÑADE ESTE DD CRÍTICO
        // Asegúrate de que la relación 'items' esté cargada antes de sumarla.
        $this->loadMissing('items'); // Forzar la carga de la relación items

      

        $newTotal = $this->items()->sum('subtotal_aplicado'); // SUMA SUBTOTTAL_APLICADO PARA REFLEJAR DESCUENTOS
     
        // Esto genera un UPDATE ventas SET importe_total = ?
        $this->update([
            'importe_total' => $newTotal,
        ]);
    }

     
    
    protected function descuentoMensualRecurrenteTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                $totalDescuentoMensual = 0;
                foreach ($this->items as $item) {
                    $item->loadMissing('servicio');

                    // SINTAXIS CORRECTA PARA COMPARAR VALOR DE ENUM
                    // Siempre compara el 'value' del objeto Enum con la cadena literal
                    if ($item->servicio && $item->servicio->tipo->value === 'recurrente') { 
                        $subtotalBaseItem = (float)$item->cantidad * (float)($item->precio_unitario ?? 0);
                        $subtotalAplicadoItem = (float)($item->subtotal_aplicado ?? $item->subtotal);
                        
                        if ($subtotalBaseItem <= 0 || $subtotalBaseItem === $subtotalAplicadoItem) {
                            continue; 
                        }
                        $descuentoMontoPorItem = $subtotalBaseItem - $subtotalAplicadoItem;
                        $totalDescuentoMensual += $descuentoMontoPorItem;
                    }
                }
                return round($totalDescuentoMensual, 2);
            }
        );
    }

    protected function descuentoUnicoTotal(): Attribute
    {
        return Attribute::make(
            get: function () {
                $totalDescuentoUnico = 0;
                foreach ($this->items as $item) {
                    $item->loadMissing('servicio');

                    // SINTAXIS CORRECTA PARA COMPARAR VALOR DE ENUM
                    if ($item->servicio && $item->servicio->tipo->value === 'unico') { 
                        $subtotalBaseItem = (float)$item->cantidad * (float)($item->precio_unitario ?? 0);
                        $subtotalAplicadoItem = (float)($item->subtotal_aplicado ?? $item->subtotal);
                        
                        if ($subtotalBaseItem <= 0 || $subtotalBaseItem === $subtotalAplicadoItem) {
                            continue; 
                        }
                        $descuentoMontoPorItem = $subtotalBaseItem - $subtotalAplicadoItem;
                        $totalDescuentoUnico += $descuentoMontoPorItem;
                    }
                }
                return round($totalDescuentoUnico, 2);
            }
        );
    }
    
    
   
}