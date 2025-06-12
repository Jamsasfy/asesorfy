<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany; // Para comentarios morfológicos
use Illuminate\Database\Eloquent\SoftDeletes; // Para borrado suave
use App\Enums\ProyectoEstadoEnum; // Si defines un Enum para los estados del proyecto

class Proyecto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'cliente_id',
        'venta_id',
        'servicio_id', // Si vinculas a Servicio recurrente genérico
        'venta_item_id', // Si vinculas al VentaItem específico
        'user_id', // Asesor asignado
        'estado',
        'fecha_inicio_estimada',
        'fecha_fin_estimada',
        'fecha_finalizacion',
        'descripcion',
    ];

    protected $casts = [
        'fecha_inicio_estimada' => 'date',
        'fecha_fin_estimada' => 'date',
        'fecha_finalizacion' => 'datetime', // 'datetime' para guardar hora también
        'estado' => ProyectoEstadoEnum::class, // Si usas un Enum para estados
    ];

    // --- Relaciones ---
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function ventaItem(): BelongsTo
    {
        return $this->belongsTo(VentaItem::class);
    }

    public function user(): BelongsTo // El usuario asignado al proyecto
    {
        return $this->belongsTo(User::class);
    }

    // --- Relación Morfológica con Comentarios ---
    public function comentarios(): MorphMany
    {
        return $this->morphMany(Comentario::class, 'comentable');
    }

    // --- Hooks --- 
    protected static function booted(): void
    {
        static::updating(function (Proyecto $proyecto) {
            if ($proyecto->isDirty('estado') && $proyecto->estado->value === ProyectoEstadoEnum::Finalizado->value && is_null($proyecto->fecha_finalizacion)) {
                $proyecto->fecha_finalizacion = now();
            }
        });

        static::updated(function (Proyecto $proyecto) {
            // Si el proyecto acaba de ser finalizado, notificar a la Venta para re-evaluar suscripciones
            if ($proyecto->wasChanged('estado') && $proyecto->estado->value === ProyectoEstadoEnum::Finalizado->value) {
                // Solo si el proyecto está vinculado a una venta
                if ($proyecto->venta) {
                    $proyecto->venta->checkAndActivateSubscriptions(); // <<< NUEVO MÉTODO en Venta
                }
            }
        });
    }
}