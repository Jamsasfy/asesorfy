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
 public function suscripciones(): HasMany
{
    return $this->hasMany(ClienteSuscripcion::class, 'venta_origen_id');
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

     
    
   /**
 * 1. Calcula el descuento total SOLO de los servicios de pago único.
 */
protected function descuentoServiciosUnicos(): Attribute
{
    return Attribute::make(get: fn (): float => $this->items
        ->where('servicio.tipo', ServicioTipoEnum::UNICO)
        ->sum(fn ($item) => ($item->cantidad * $item->precio_unitario) - $item->subtotal_aplicado)
    );
}

/**
 * 2. Calcula el descuento que se aplicará en UNA cuota mensual.
 */
protected function descuentoRecurrenteMensual(): Attribute
{
    return Attribute::make(get: fn (): float => $this->items
        ->where('servicio.tipo', ServicioTipoEnum::RECURRENTE)
        ->sum(fn ($item) => ($item->cantidad * $item->precio_unitario) - $item->subtotal_aplicado)
    );
}

/**
 * 3. Calcula el AHORRO TOTAL para el cliente de los descuentos recurrentes.
 * (Descuento de un mes * número de meses de la oferta)
 */
protected function ahorroTotalRecurrente(): Attribute
{
    return Attribute::make(get: function (): float {
        $ahorroTotal = $this->items
            ->where('servicio.tipo', ServicioTipoEnum::RECURRENTE)
            ->sum(function ($item) {
                $descuentoMensualItem = ($item->cantidad * $item->precio_unitario) - $item->subtotal_aplicado;
                $meses = $item->descuento_duracion_meses ?? 1;
                return $descuentoMensualItem * $meses;
            });
        return round($ahorroTotal, 2);
    });
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
    $this->loadMissing('items.servicio.departamento.coordinador', 'cliente');

    $ventaRequiereProyecto = $this->items->contains(fn ($item) =>
        $item->servicio?->requiere_proyecto_activacion
    );

    foreach ($this->items as $item) {
        $servicio = $item->servicio;
        if (!$servicio) continue;

        // --- Lógica de Proyectos y Notificación Dirigida ---
        if ($servicio->requiere_proyecto_activacion) {
            
            $proyecto = Proyecto::create([
                'nombre'          => "{$servicio->nombre} ({$this->cliente->razon_social})",
                'cliente_id'      => $this->cliente_id,
                'venta_id'        => $this->id,
                'venta_item_id'   => $item->id,
                'servicio_id'     => $servicio->id,
                'estado'          => ProyectoEstadoEnum::Pendiente,
                'descripcion'     => "Proyecto generado por la venta #{$this->id} para el servicio '{$servicio->nombre}'.",
            ]);

            // ▼▼▼ LÓGICA DE NOTIFICACIÓN CORREGIDA ▼▼▼

            // 1. Obtenemos al coordinador del departamento del servicio
            $coordinadorDelDepto = $servicio->departamento?->coordinador;

            // 2. Si existe, le enviamos la notificación solo a él
            if ($coordinadorDelDepto) {
                Notification::make()
                    ->title('Nuevo Proyecto Pendiente')
                    ->body("El proyecto '{$proyecto->nombre}' de tu departamento necesita un responsable.")
                    ->icon('heroicon-o-clipboard-document-list')
                    ->actions([
                        Action::make('view')
                            ->label('Ver Proyecto')
                            ->url(ProyectoResource::getUrl('view', ['record' => $proyecto]))
                            ->markAsRead()->close(),
                    ])
                    ->sendToDatabase($coordinadorDelDepto);
            }
        }

        // --- Lógica de Creación de Suscripciones (sin cambios) ---
        $estadoInicial = null;
        $fechaInicio = null;
        $fechaFin = null;

        if ($servicio->tipo === ServicioTipoEnum::UNICO) {
            $estadoInicial = ClienteSuscripcionEstadoEnum::ACTIVA;
            $fechaInicio = now();
            $fechaFin = now();
        } elseif ($servicio->tipo === ServicioTipoEnum::RECURRENTE) {
            $estadoInicial = $ventaRequiereProyecto ? ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION : ClienteSuscripcionEstadoEnum::ACTIVA;
            $fechaInicio = $ventaRequiereProyecto ? null : ($item->fecha_inicio_servicio ?? now());
            $fechaFin = null;
        }

        if ($estadoInicial) {
            ClienteSuscripcion::create([
                'cliente_id'             => $this->cliente_id,
                'servicio_id'            => $item->servicio_id,
                'venta_origen_id'        => $this->id,
                'es_tarifa_principal'    => $servicio->es_tarifa_principal,
                'precio_acordado'        => $item->subtotal_aplicado,
                'cantidad'               => $item->cantidad,
                'fecha_inicio'           => $fechaInicio,
                'fecha_fin'              => $fechaFin,
                'estado'                 => $estadoInicial,
                'ciclo_facturacion'      => $servicio->ciclo_facturacion, // Asegúrate de que este campo exista y se rellene
                'descuento_tipo'         => $item->descuento_tipo,
                'descuento_valor'        => $item->descuento_valor,
                'descuento_descripcion'  => $item->observaciones_descuento,
                'descuento_valido_hasta' => $item->descuento_valido_hasta,
                'observaciones'          => $item->observaciones_item,
            ]);
        }
    }
}

  

}