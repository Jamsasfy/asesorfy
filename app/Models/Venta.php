<?php

namespace App\Models;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Enums\VentaCorreccionEstadoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute; // Si usas accessors/mutators
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use App\Filament\Resources\ProyectoResource;



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
         'correccion_estado',
    'correccion_solicitada_at',
    'correccion_solicitada_por_id',
    'correccion_motivo',
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'importe_total' => 'decimal:2', // Asegura que se maneja como decimal
        'correccion_estado' => VentaCorreccionEstadoEnum::class,
    'correccion_solicitada_at' => 'datetime',
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

    public function solicitanteCorreccion(): BelongsTo
{
    return $this->belongsTo(User::class, 'correccion_solicitada_por_id');
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
 public function suscripciones(): HasMany
{
    return $this->hasMany(ClienteSuscripcion::class, 'venta_origen_id');
}

public function facturas(): HasMany
{
    return $this->hasMany(Factura::class);
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
     * Método para recalcular y guardar el importe total en la Venta.
     */
    public function updateTotal(): void
    {
        // Asegura que la relación 'items' esté cargada para sumar
        $this->loadMissing('items'); 
        
        // Suma el campo 'subtotal_aplicado' de cada item, que ya tiene los descuentos.
        $newTotal = $this->items->sum('subtotal_aplicado'); 

        $this->importe_total = $newTotal;

        // Usamos saveQuietly() para guardar el cambio sin disparar más eventos 'updated'
        // y así evitar posibles bucles infinitos.
        $this->saveQuietly();
    }
    /**
     * Este método es llamado por el modelo Proyecto cuando se finaliza.
    
     */
   public function checkAndActivateSubscriptions(): void
{
    // Obtener los items de esta venta cuyo SERVICIO sea tarifa principal y recurrente
    $ventaItems = $this->items()
        ->whereHas('servicio', function (Builder $query) {
            $query->where('tipo', \App\Enums\ServicioTipoEnum::RECURRENTE)
                  ->where('es_tarifa_principal', true); // <-- Condición movida aquí dentro
        })
        ->get();

    // El resto de la lógica no necesita cambios...
    foreach ($ventaItems as $item) {
        $suscripcion = ClienteSuscripcion::where('cliente_id', $this->cliente_id)
            ->where('servicio_id', $item->servicio_id)
            ->where('venta_origen_id', $this->id)
            ->where('estado', \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
            ->first();

        if (! $suscripcion) {
            continue;
        }

        $proyectosIncompletos = $this->proyectos()
            ->whereNot('estado', \App\Enums\ProyectoEstadoEnum::Finalizado)
            ->exists();

        if (! $proyectosIncompletos) {
            $suscripcion->estado = \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA;
            $suscripcion->fecha_inicio = now();
            $suscripcion->save();
        }
    }
}  

protected function importeBaseSinDescuento(): Attribute
{
    return Attribute::make(
        // Suma el campo 'subtotal' de cada item, que es el precio original sin descuento
        get: fn (): float => $this->items->sum('subtotal')
    );
}
/**
 * Calcula el importe total de la venta CON IVA.
 */
protected function importeTotalConIva(): Attribute
{
    return Attribute::make(
        get: function (): float {
            // Usamos un IVA del 21% por defecto.
            // Puedes cambiarlo o hacerlo dinámico si es necesario.
            $iva = 1.21;
            return round($this->importe_total * $iva, 2);
        }
    );
}

/**
     * Orquesta la creación de proyectos y suscripciones después de que una venta
     * se haya guardado completamente (incluyendo sus items).
     */
public function processSaleAfterCreation(): void
{
    // Precargamos relaciones para que las consultas sean más eficientes
    $this->loadMissing('items.servicio', 'cliente');

    // 1. Decidimos si la VENTA COMPLETA requiere un proyecto.
    $ventaRequiereProyecto = $this->items->contains(function ($item) {
        if (!$item->servicio) return false;

        // Si el servicio es editable, la decisión la toma el campo del item.
        // Si no, la decisión la toma el campo del servicio.
        return $item->servicio->es_editable
            ? $item->requiere_proyecto
            : $item->servicio->requiere_proyecto_activacion;
    });

    // 2. Recorremos cada item para crear proyectos y suscripciones
    foreach ($this->items as $item) {
        $servicio = $item->servicio;
        if (!$servicio) continue;

        // --- Lógica para decidir si ESTE ITEM necesita un proyecto ---
        $debeCrearProyecto = $servicio->es_editable
            ? $item->requiere_proyecto
            : $servicio->requiere_proyecto_activacion;

        if ($debeCrearProyecto) {
            // Usamos el nombre personalizado si existe, si no, el del servicio.
            $nombreDelProyecto = $item->nombre_personalizado ?: $servicio->nombre;

            $proyecto = \App\Models\Proyecto::create([
                'nombre'          => "{$nombreDelProyecto} ({$this->cliente->razon_social})",
                'cliente_id'      => $this->cliente_id,
                'venta_id'        => $this->id,
                'venta_item_id'   => $item->id,
                'servicio_id'     => $servicio->id,
                'estado'          => \App\Enums\ProyectoEstadoEnum::Pendiente,
                'descripcion'     => "Proyecto generado por la venta #{$this->id}.",
            ]);

            // Lógica de notificación al coordinador...
            $coordinadores = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'coordinador'))->get();
            if ($coordinadores->isNotEmpty()) {
                \Filament\Notifications\Notification::make()
                    ->title('Nuevo Proyecto Pendiente')
                    ->body("El proyecto '{$proyecto->nombre}' necesita un responsable.")
                    ->actions([\Filament\Notifications\Actions\Action::make('view')->label('Ver Proyecto')->url(\App\Filament\Resources\ProyectoResource::getUrl('view', ['record' => $proyecto]))])
                    ->sendToDatabase($coordinadores);
            }
        }

        // --- Lógica de Creación de Suscripciones ---
        $estadoInicial = null;
        $fechaInicio = null;
        $fechaFin = null;

        if ($servicio->tipo === \App\Enums\ServicioTipoEnum::UNICO) {
            $estadoInicial = \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA;
            $fechaInicio = now();
            $fechaFin = now();
        } elseif ($servicio->tipo === \App\Enums\ServicioTipoEnum::RECURRENTE) {
            $estadoInicial = $ventaRequiereProyecto ? \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION : \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA;
            $fechaInicio = $ventaRequiereProyecto ? null : ($item->fecha_inicio_servicio ?? now());
            $fechaFin = null;
        }

        if ($estadoInicial) {
            \App\Models\ClienteSuscripcion::create([
                'cliente_id'             => $this->cliente_id,
                'servicio_id'            => $item->servicio_id,
                'venta_origen_id'        => $this->id,
                'nombre_personalizado'   => $item->nombre_personalizado,
                'es_tarifa_principal'    => $servicio->es_tarifa_principal,
                'precio_acordado'        => $item->subtotal_aplicado,
                'cantidad'               => $item->cantidad,
                'fecha_inicio'           => $fechaInicio,
                'fecha_fin'              => $fechaFin,
                'estado'                 => $estadoInicial,
                'ciclo_facturacion'      => $servicio->ciclo_facturacion,
                'descuento_tipo'         => $item->descuento_tipo,
                'descuento_valor'        => $item->descuento_valor,
                'descuento_duracion_meses' => $item->descuento_duracion_meses, // <-- LÍNEA AÑADIDA
                'descuento_descripcion'  => $item->observaciones_descuento,
                'descuento_valido_hasta' => $item->descuento_valido_hasta,
                'observaciones'          => $item->observaciones_item,
            ]);
        }
    }
}
  

}