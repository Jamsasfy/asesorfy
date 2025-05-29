<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute; // Si usas accessors/mutators
use App\Models\Cliente;

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

    // --- Hooks para calcular importe_total automáticamente ---
    // Necesitamos configurar estos hooks en el modelo VentaItem, no aquí.
    // Los items notificarán a la Venta cuando cambien.

     // Método para recalcular y guardar el importe total
     public function updateTotal(): void
     {
         $newTotal = $this->items()->sum('subtotal');
     
         // Esto genera un UPDATE ventas SET importe_total = ?
         $this->update([
             'importe_total' => $newTotal,
         ]);
     }
}