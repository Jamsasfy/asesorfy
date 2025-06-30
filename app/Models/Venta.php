<?php

namespace App\Models;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Enums\ServicioTipoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute; // Si usas accessors/mutators
use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

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

     // Relación de Venta con Proyectos (nueva)
    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class);
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

     /**
     * Método para recalcular y guardar el importe total en la Venta
     * y para crear los Proyectos de activación asociados.
     */
   public function updateTotal(): void
    {
        // === 1. Calcular y guardar el importe total de la Venta (CON DESCUENTO, SIN IVA) ===
        $this->loadMissing('items'); // Asegura que la relación 'items' esté cargada
        $newTotal = $this->items->sum(function($item) {
            // Suma 'subtotal_aplicado' (que ya incluye el descuento y es sin IVA)
            return (float)($item->subtotal_aplicado ?? $item->subtotal ?? 0); 
        });
        $this->importe_total = $newTotal;
        $this->save(); // Guarda el modelo Venta con el nuevo total

        // === 2. Crear/Actualizar Proyectos de Activación (Para ítems que los requieran) ===
        $this->items->each(function($item) {
            // Precargar el servicio para acceder a su tipo y si requiere proyecto.
            $item->loadMissing('servicio');

            // Solo si el item tiene un servicio que 'requiere_proyecto_activacion'
            // Este servicio es el que DISPARA la creación de un proyecto (generalmente de tipo ÚNICO)
            if ($item->servicio && $item->servicio->requiere_proyecto_activacion) { 
                // updateOrCreate para crear el proyecto si no existe o actualizarlo si ya existe
                $proyecto = Proyecto::updateOrCreate(
                    ['venta_item_id' => $item->id], // Clave única para encontrar el proyecto
                    [
                        'nombre' => sprintf(
                            '%s (%s)',
                            $item->servicio->nombre,
                            $this->cliente->dni_cif ?? '-'
                        ),
                        'cliente_id' => $this->cliente_id,
                        'venta_id' => $this->id, // Vincular a esta venta
                        'servicio_id' => $item->servicio_id, // Vincular al servicio proyectable
                        'user_id' => null, // Asignar al comercial de la venta por defecto
                        'estado' => ProyectoEstadoEnum::Pendiente->value, // Estado inicial del proyecto
                        'descripcion' => "Proyecto generado por la venta {$this->id} para el servicio '{$item->servicio->nombre}'.",
                        // Otros campos del proyecto (fechas estimadas, etc.) si los tienes
                    ]
                );
            }
        });

        // Este método checkAndActivateSubscriptions() estará VACÍO POR AHORA, pero existe.
        // Lo rellenaremos cuando estemos listos para la activación de suscripciones.
        // La llamada a this->checkAndActivateSubscriptions() se añade en el hook 'updated' del modelo Proyecto.
    }

    /**
     * Este método es llamado por el modelo Proyecto cuando se finaliza.
    
     */
    public function checkAndActivateSubscriptions(): void
    {
       
        // Obtener los items de esta venta que sean tarifas principales y recurrentes
    $ventaItems = $this->items()
        ->where('es_tarifa_principal', true)
        ->whereHas('servicio', function ($query) {
            $query->where('tipo', ServicioTipoEnum::RECURRENTE);
        })
        ->get();

    foreach ($ventaItems as $item) {
        // Buscar la suscripción pendiente para este cliente + servicio + venta
        $suscripcion = ClienteSuscripcion::where('cliente_id', $this->cliente_id)
            ->where('servicio_id', $item->servicio_id)
            ->where('venta_origen_id', $this->id)
            ->where('estado', ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
            ->first();

        if (! $suscripcion) {
            continue; // No hay suscripción pendiente para este item
        }

        // Verificar si aún hay proyectos activos relacionados a esta venta
        $proyectosIncompletos = $this->proyectos()
            ->whereNot('estado', ProyectoEstadoEnum::Finalizado)
            ->exists();

        if (! $proyectosIncompletos) {
            // Si todos los proyectos están finalizados, activar la suscripción
            $suscripcion->estado = ClienteSuscripcionEstadoEnum::ACTIVA;
            $suscripcion->fecha_inicio = now();
            $suscripcion->save();

            // Opcional: log o notificación
            // Log::info("Suscripción activada automáticamente para el cliente ID {$this->cliente_id} y servicio ID {$item->servicio_id}");
        }
    }

    
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


public function crearSuscripcionesDesdeItems(): void
{
    // 1) Precargamos items y sus servicios
    $this->loadMissing(['items.servicio']);

    // 2) ¿Esta venta incluye algún servicio que requiera proyecto?
    $ventaRequiereProyecto = $this->items->contains(fn ($item) =>
        $item->servicio?->requiere_proyecto_activacion
    );

    foreach ($this->items as $item) {
        $servicio = $item->servicio;
        if (! $servicio) {
            continue;
        }

        $isRec  = $servicio->tipo->value === ServicioTipoEnum::RECURRENTE->value;
        $isPri  = $servicio->es_tarifa_principal;

        // 3) Recuperar o crear la suscripción
        $sus = ClienteSuscripcion::firstOrNew([
            'cliente_id'  => $this->cliente_id,
            'servicio_id' => $servicio->id,
        ]);

        // 4) Cantidad: acumulamos solo si ya existía y es recurrente no-principal
        if ($sus->exists && $isRec && ! $isPri) {
            $sus->cantidad += $item->cantidad;
        } else {
            $sus->cantidad = $item->cantidad;
        }

        // 5) Campos comunes
        $sus->precio_acordado        = $item->precio_unitario_aplicado;
        $sus->descuento_tipo         = $item->descuento_tipo;
        $sus->descuento_valor        = $item->descuento_valor;
        $sus->descuento_descripcion  = $item->observaciones_descuento;
        $sus->descuento_valido_hasta = $item->descuento_valido_hasta;
        $sus->observaciones          = $item->observaciones_item;
        $sus->ciclo_facturacion      = $servicio->ciclo_facturacion;

        // 6) Estado y fechas
        if ($isRec && $isPri && $ventaRequiereProyecto) {
            // Caso CLAVE: recurrente-principal y la venta tiene un ítem con proyecto
            $sus->estado                   = ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION;
            $sus->fecha_inicio             = null;
            $sus->proxima_fecha_facturacion = null;
        } elseif (! $isRec) {
            // Servicio único → activa ya
            $sus->estado       = ClienteSuscripcionEstadoEnum::ACTIVA;
            $sus->fecha_inicio = now();
            $sus->fecha_fin    = now();
        } else {
            // Recurrente (no-principal, o principal cuando no hay proyecto)
            $sus->estado                   = ClienteSuscripcionEstadoEnum::ACTIVA;
            $sus->fecha_inicio             = $item->fecha_inicio_servicio ?? now();
            $sus->proxima_fecha_facturacion = null;
        }

        // 7) Asegurar tarifa principal única
        if ($isPri && $sus->estado === ClienteSuscripcionEstadoEnum::ACTIVA) {
            // Desmarcar cualquier otra
            ClienteSuscripcion::where('cliente_id', $this->cliente_id)
                ->where('es_tarifa_principal', true)
                ->update(['es_tarifa_principal' => false]);
            $sus->es_tarifa_principal = true;
        }

        // 8) Guardar
        $sus->save();
    }
}

protected static function booted()
{
    static::creating(function (Venta $venta) {
        foreach ($venta->items as $item) {
            $servicio = $item->servicio;
            if (
                $servicio
                && $servicio->tipo->value === ServicioTipoEnum::RECURRENTE->value
                && $servicio->es_tarifa_principal
                && ClienteSuscripcion::where([
                    ['cliente_id', $venta->cliente_id],
                    ['servicio_id', $servicio->id],
                    ['es_tarifa_principal', true],
                ])
                ->whereIn('estado', [
                    ClienteSuscripcionEstadoEnum::ACTIVA->value,
                    ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION->value,
                ])
                ->exists()
            ) {
                throw ValidationException::withMessages([
                    'items' => "El cliente ya tiene activa o pendiente la suscripción “{$servicio->nombre}”.",
                ]);
            }
        }
    });
}

  

}