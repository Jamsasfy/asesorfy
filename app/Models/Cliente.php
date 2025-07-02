<?php

namespace App\Models;

use App\Enums\ServicioTipoEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Enums\ClienteEstadoEnum;


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
        'observaciones',
        'estado',
        'fecha_alta',
        'fecha_baja',
        'lead_id',
        'comercial_id',
       
    ];

// Dentro de la clase Cliente
protected $casts = [
    'estado' => ClienteEstadoEnum::class,
    // ... otros casts que ya tengas
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
public function documentosPolimorficos()
{
    return $this->morphMany(\App\Models\Documento::class, 'documentable');
}


  /**
     * ACCESOR MEJORADO: Obtiene la suscripción de la tarifa principal activa.
     * Busca directamente en la tabla `cliente_suscripciones` que es la fuente de la verdad.
     */
    protected function tarifaPrincipalActiva(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->suscripciones()
                ->where('es_tarifa_principal', true)
                ->where('estado', \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA)
                ->first()
        );
    }

    /**
     * ACCESOR MEJORADO: Devuelve el nombre del servicio y el precio formateado.
     * Utiliza el accesor anterior que es mucho más rápido.
     */
    protected function tarifaPrincipalActivaConPrecio(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Llama al nuevo y eficiente accesor 'tarifaPrincipalActiva'
                $suscripcionActiva = $this->tarifa_principal_activa; 
    
                if ($suscripcionActiva && $suscripcionActiva->servicio) {
                    $acronimo = $suscripcionActiva->servicio->acronimo ?? $suscripcionActiva->servicio->nombre;
                    $precioFormateado = number_format($suscripcionActiva->precio_acordado, 2, ',', '.') . ' €';
    
                    return "{$acronimo} - {$precioFormateado}";
                }
                
                return 'Sin tarifa principal';
            }
        );
    }
   
protected static function booted(): void
{
    static::deleting(function (Cliente $cliente) {
        // 🗑️ Comentarios
        $cliente->comentarios()->delete();

        // 🗑️ Documentos normales
        $cliente->documentos()->each(fn ($doc) => $doc->delete());

        // 🗑️ Documentos polimórficos
        $cliente->documentosPolimorficos()->each(fn ($doc) => $doc->delete());

        // 🗑️ Ventas
        $cliente->ventas()->each(fn ($venta) => $venta->delete());

        // 🗑️ Suscripciones
        $cliente->suscripciones()->each(fn ($suscripcion) => $suscripcion->delete());

        // 👥 Usuarios con acceso a este cliente
        foreach ($cliente->usuarios as $usuario) {
            $otrosClientes = $usuario->clientes()->where('clientes.id', '!=', $cliente->id)->exists();

            if (! $otrosClientes) {
                // Solo se elimina si no tiene más accesos
                $usuario->delete();
            }
        }

        // 🔓 Limpieza de la tabla pivote
        $cliente->usuarios()->detach();
    });
}



}
