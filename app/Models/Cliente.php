<?php

namespace App\Models;

use App\Enums\ServicioTipoEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Cliente extends Model
{

    protected $fillable = [
        'user_id',
        'tipo_cliente_id',
        'nombre',
        'apellidos',
        'razon_social',
        'dni_cif',
        'email_contacto',
        'telefono_contacto',
        'direccion',
        'codigo_postal',
        'localidad',
        'provincia',
        'comunidad_autonoma',
        'iban_asesorfy',
        'iban_impuestos',
        'ccc',
        'asesor_id',
        'coordinador_id',
        'observaciones',
        'estado',
        'fecha_alta',
        'fecha_baja',
        'lead_id',
        'comercial_id',
       
    ];


    public function user()
{
    return $this->belongsTo(User::class);
}

public function tipoCliente()
{
    return $this->belongsTo(TipoCliente::class, 'tipo_cliente_id');
}

public function asesor()
{
    return $this->belongsTo(User::class, 'asesor_id');
}

public function coordinador()
{
    return $this->belongsTo(User::class, 'coordinador_id');
}

public function usuarios(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'cliente_user');
}

public function recordTitle(): string
{
    return $this->razon_social ?? 'Cliente sin nombre';
}

public function documentos(): HasMany
{
    return $this->hasMany(\App\Models\Documento::class);
}

public function comentarios(): MorphMany
{
    return $this->morphMany(Comentario::class, 'comentable');
}

public function lead(): BelongsTo
{
    return $this->belongsTo(Lead::class);
}

 // Relación uno-a-muchos con Ventas (todas las ventas de este cliente)
 public function ventas(): HasMany
 {
     return $this->hasMany(Venta::class);
 }
 /**
 * Un Cliente puede tener muchas suscripciones.
 */
public function suscripciones(): HasMany
{
    return $this->hasMany(ClienteSuscripcion::class);
}


 //accessor para ver el servicio que tiene activo y su precio

  public function getTarifaPrincipalActivaAttribute(): ?VentaItem // O puedes devolver Servicio si lo prefieres
    {
        $hoy = now()->startOfDay(); // Para comparaciones de fechas

        // Obtener la venta más reciente que contenga una tarifa principal activa
        $ventaConTarifaActiva = $this->ventas()
            ->whereHas('items.servicio', function ($query) {
                $query->where('tipo', ServicioTipoEnum::RECURRENTE)
                      ->where('es_tarifa_principal', true);
            })
            // Considerar la fecha de inicio del servicio del item o la fecha de venta.
            // Esta parte puede necesitar ajuste según cómo uses VentaItem.fecha_inicio_servicio
            ->where(function ($query) use ($hoy) {
                $query->whereHas('items', function ($subQuery) use ($hoy) {
                    // Si VentaItem.fecha_inicio_servicio es la que manda y está presente
                    $subQuery->whereNotNull('fecha_inicio_servicio')
                             ->where('fecha_inicio_servicio', '<=', $hoy);
                })
                // O si VentaItem.fecha_inicio_servicio es null, usamos Venta.fecha_venta
                ->orWhereDoesntHave('items', function ($subQuery) {
                     // Asegurarse que el item que no tiene fecha_inicio_servicio es el principal
                    $subQuery->whereNotNull('fecha_inicio_servicio')
                             ->whereHas('servicio', function ($sQuery) {
                                 $sQuery->where('tipo', ServicioTipoEnum::RECURRENTE)
                                        ->where('es_tarifa_principal', true);
                             });
                })
                ->where('fecha_venta', '<=', $hoy); // La venta debe haber ocurrido ya
            })
            ->orderByDesc('fecha_venta') // La venta más reciente
            ->orderByDesc('id')          // Desempate por si hay varias en la misma fecha
            ->first();

        if ($ventaConTarifaActiva) {
            // Ahora, de esa venta, obtenemos el VentaItem específico de la tarifa principal
            $itemTarifa = $ventaConTarifaActiva->items()
                ->whereHas('servicio', function ($query) {
                    $query->where('tipo', ServicioTipoEnum::RECURRENTE)
                          ->where('es_tarifa_principal', true);
                })
                // Asegurarse de que este item específico esté activo según su propia fecha de inicio si la tiene
                ->where(function ($query) use ($hoy) {
                    $query->whereNull('fecha_inicio_servicio') // Si es null, se rige por fecha_venta (ya filtrada)
                          ->orWhere('fecha_inicio_servicio', '<=', $hoy);
                })
                ->orderByDesc('id') // En caso de múltiples items principales en una venta (no debería pasar)
                ->first();

            return $itemTarifa; // Esto devuelve el VentaItem completo
        }

        return null;
    }

    // Podrías querer un accesor que devuelva solo el Servicio:
    public function getServicioTarifaPrincipalActivaAttribute(): ?Servicio
    {
        return $this->tarifa_principal_activa?->servicio;
    }

    // Y otro para el precio acordado de esa tarifa:
    public function getPrecioTarifaPrincipalActivaAttribute(): ?float // O string si usas decimal
    {
        return $this->tarifa_principal_activa?->precio_unitario;
    }

protected function tarifaPrincipalActivaConPrecio(): Attribute
    {
        return Attribute::make(
            get: function () {
                $tarifaItem = $this->tarifa_principal_activa; // Esto llama a tu accesor existente

                if ($tarifaItem && $tarifaItem->servicio) {
                    $acronimo = $tarifaItem->servicio->acronimo; // Llama al accesor 'acronimo' en Servicio
                    
                    // Formatear el precio. Puedes ajustarlo a tus necesidades.
                    // number_format(numero, decimales, separador_decimal, separador_miles)
                    $precioFormateado = number_format($tarifaItem->precio_unitario, 2, ',', '.') . ' €';
                    
                    return "{$acronimo} - {$precioFormateado}";
                }
                
                return null; // O 'Ninguna', o lo que prefieras si no hay tarifa
            }
        );
    }
   

}
