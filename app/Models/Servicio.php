<?php

namespace App\Models;

use App\Enums\ServicioTipoEnum; // Importar el Enum
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servicio extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo',
        'precio_base',
        'activo',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Castear el campo 'tipo' a nuestro Enum
        'tipo' => ServicioTipoEnum::class,
        // Castear 'activo' a booleano
        'activo' => 'boolean',
        // Castear 'precio_base' a decimal con 2 decimales (opcional pero bueno para consistencia)
        'precio_base' => 'decimal:2',
    ];


    // Relación uno-a-muchos con VentaItems (los items de venta que usan este servicio)
    public function items(): HasMany
    {
        return $this->hasMany(VentaItem::class);
    }


}