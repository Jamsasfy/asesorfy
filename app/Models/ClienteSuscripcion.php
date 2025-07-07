<?php

namespace App\Models;

use App\Enums\CicloFacturacionEnum;
use App\Enums\ClienteSuscripcionEstadoEnum; // <-- Importamos nuestro Enum
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute; 

class ClienteSuscripcion extends Model
{
    use HasFactory;

    protected $table = 'cliente_suscripciones';


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cliente_id',
        'servicio_id',
        'venta_origen_id',
        'es_tarifa_principal',
        'precio_acordado',
        'cantidad',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'descuento_tipo',
        'descuento_valor',
        'descuento_descripcion',
        'descuento_valido_hasta',
        'observaciones',
        'stripe_subscription_id',
        'ciclo_facturacion',
        'proxima_fecha_facturacion',
        'datos_adicionales',
         'nombre_personalizado',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'es_tarifa_principal' => 'boolean',
        'precio_acordado' => 'decimal:2',
        'descuento_valor' => 'decimal:2',
        'cantidad' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'descuento_valido_hasta' => 'date',
        'proxima_fecha_facturacion' => 'date',
        'datos_adicionales' => 'array',
        'estado' => ClienteSuscripcionEstadoEnum::class, // <-- Usamos el Enum para el estado
        'ciclo_facturacion' => CicloFacturacionEnum::class, // Este es el que falta

    ];

    // --- RELACIONES ---

    /**
     * La suscripción pertenece a un Cliente.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * La suscripción está asociada a un Servicio.
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /**
     * La suscripción fue originada por una Venta (opcional).
     */
    public function ventaOrigen(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_origen_id');
    }

    /**
     * Devuelve el nombre final del servicio, usando el personalizado si existe.
     */
    protected function nombreFinal(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->nombre_personalizado ?: $this->servicio?->nombre
        );
    }


}