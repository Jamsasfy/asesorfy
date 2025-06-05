<?php

namespace App\Models;

use App\Enums\ServicioTipoEnum; // Importar el Enum
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute; // Asegúrate de tener este use


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
        'es_tarifa_principal', // Este campo es opcional, solo si lo necesitas
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

    //hacer acronimos para los nombres largos

     protected function acronimo(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (empty($attributes['nombre'])) {
                    return '';
                }
                $words = preg_split("/\s+/", $attributes['nombre']); // Divide por uno o más espacios
                $acronym = '';
                foreach ($words as $word) {
                    if (!empty($word)) {
                        // mb_substr para manejar correctamente caracteres multibyte (como tildes, si las hubiera al inicio)
                        $acronym .= mb_strtoupper(mb_substr($word, 0, 1));
                    }
                }
                return $acronym;
            }
        );
    }


}