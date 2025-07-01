<?php

namespace App\Models;

use App\Enums\ClienteSuscripcionEstadoEnum;

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



/**
     * Orquesta la creación de proyectos y suscripciones después de que una venta
     * se haya guardado completamente (incluyendo sus items).
     */
    public function processSaleAfterCreation(): void
    {
        $this->loadMissing('items.servicio', 'cliente');

        $ventaRequiereProyecto = $this->items->contains(fn ($item) =>
            $item->servicio?->requiere_proyecto_activacion
        );

        foreach ($this->items as $item) {
            $servicio = $item->servicio;
            if (!$servicio) continue;

            // --- Lógica de Proyectos (Sin cambios) ---
            if ($servicio->requiere_proyecto_activacion) {
                $proyecto = Proyecto::create([
                    'nombre'          => "{$servicio->nombre} ({$this->cliente->razon_social})",
                    'cliente_id'      => $this->cliente_id,
                    'venta_id'        => $this->id,
                    'venta_item_id'   => $item->id,
                    'servicio_id'     => $servicio->id,
                    'estado'          => \App\Enums\ProyectoEstadoEnum::Pendiente,
                    'descripcion'     => "Proyecto generado por la venta #{$this->id} para el servicio '{$servicio->nombre}'.",
                ]);

                 // 2. Preparamos y enviamos la notificación a los coordinadores
                $coordinadores = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'coordinador'))->get();

                if ($coordinadores->isNotEmpty()) {
                    Notification::make()
                        ->title('Nuevo Proyecto Pendiente')
                        ->body("Proyecto '{$proyecto->nombre}' pendiente asignación.")
                        ->icon('heroicon-o-briefcase') // Icono de proyecto
                        ->color('info') // Color azul para notificaciones informativas
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->label('Ver Proyecto')
                                ->url(\App\Filament\Resources\ProyectoResource::getUrl('view', ['record' => $proyecto])),
                        ])
                        ->sendToDatabase($coordinadores);
                }
            }

           
            // AHORA CREA SUSCRIPCIÓN PARA TODOS LOS TIPOS DE SERVICIO
            
            $estadoInicial = null;
            $fechaInicio = null;
            $fechaFin = null;

            if ($servicio->tipo === ServicioTipoEnum::UNICO) {
                // Para servicios únicos, la suscripción se crea activa y se cierra al instante.
                // Esto sirve para registrarlo y poder facturarlo.
                $estadoInicial = ClienteSuscripcionEstadoEnum::ACTIVA;
                $fechaInicio = now();
                $fechaFin = now(); // El servicio empieza y termina hoy.

            } elseif ($servicio->tipo === ServicioTipoEnum::RECURRENTE) {
                // Para servicios recurrentes, aplicamos la lógica de activación por proyecto.
                $estadoInicial = $ventaRequiereProyecto
                    ? ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION
                    : ClienteSuscripcionEstadoEnum::ACTIVA;

                $fechaInicio = $ventaRequiereProyecto
                    ? null
                    : ($item->fecha_inicio_servicio ?? now());
                
                $fechaFin = null; // Los recurrentes no tienen fecha de fin por defecto.
            }

            // Creamos el registro solo si hemos determinado un estado
            if ($estadoInicial) {
                ClienteSuscripcion::create([
                    'cliente_id'             => $this->cliente_id,
                    'servicio_id'            => $item->servicio_id,
                    'venta_origen_id'        => $this->id,
                    'es_tarifa_principal'    => $servicio->es_tarifa_principal,
                    'precio_acordado'        => $item->precio_unitario_aplicado,
                    'cantidad'               => $item->cantidad,
                    'fecha_inicio'           => $fechaInicio,
                    'fecha_fin'              => $fechaFin, // <-- Se añade la fecha de fin
                    'estado'                 => $estadoInicial,
                    'ciclo_facturacion'      => 'mensual',
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