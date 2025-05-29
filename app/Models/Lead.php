<?php

namespace App\Models;

// Importaciones necesarias
use App\Enums\LeadEstadoEnum; // Importar el Enum de Estados
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Importar BelongsTo para relaciones
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

// Asumiendo que estos modelos existen en App\Models, ajusta si es necesario
// use App\Models\User;
// use App\Models\Procedencia;
// use App\Models\MotivoDescarte;
// use App\Models\Cliente;
// use App\Models\Servicio;

class Lead extends Model
{
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     * Comenta esto y usa $fillable si prefieres esa estrategia.
     * @var array<string>|bool
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     * Define cómo Laravel debe tratar ciertos campos.
     * @var array<string, string>
     */
    protected $casts = [
        'estado' => LeadEstadoEnum::class, // Convierte entre string y objeto Enum
        'fecha_gestion' => 'datetime',     // Convierte a objeto Carbon/DateTime
        'agenda' => 'datetime',            // Convierte a objeto Carbon/DateTime
        'fecha_cierre' => 'datetime',      // Convierte a objeto Carbon/DateTime
        'llamadas' => 'integer',           // Asegura que es un número entero
        'emails' => 'integer',            // Asegura que es un número entero
        'chats' => 'integer',             // Asegura que es un número entero
        'otros_acciones' => 'integer',     // Asegura que es un número entero
    ];

      // --- Listener de Evento para fecha_gestion ---
    /**
     * The "booted" method of the model.
     * Se ejecuta cuando el modelo se inicializa.
     */
    protected static function booted(): void
    {
        // Escucha el evento 'updating' (justo antes de que se guarde una actualización)
        static::updating(function (Lead $lead) {
            // Comprobamos varias condiciones:
            // 1. Si el campo 'estado' ha cambiado realmente en esta actualización
            // 2. Si el valor ORIGINAL de 'estado' ANTES de esta actualización era SIN_GESTIONAR
            // 3. Si el campo 'fecha_gestion' todavía está VACÍO (es null)
            if (
                $lead->isDirty('estado') &&
                $lead->getOriginal('estado') === LeadEstadoEnum::SIN_GESTIONAR &&
                is_null($lead->fecha_gestion)
            ) {
                // Si se cumplen las 3 condiciones, es la primera vez que sale de SIN_GESTIONAR
                // Establecemos la fecha de gestión a la hora actual.
                $lead->fecha_gestion = now();
            }
        });
    }
    // --- Fin Listener ---

    

    // --- RELACIONES BelongsTo ---
    // Definen a qué otros modelos pertenece este Lead

    /**
     * Obtiene la procedencia del lead.
     * Relación con la tabla 'procedencias' a través de 'procedencia_id'.
     */
    public function procedencia(): BelongsTo
    {
        // Asegúrate que el modelo 'Procedencia' existe en App\Models
        return $this->belongsTo(Procedencia::class);
    }

    /**
     * Obtiene el usuario que creó el lead.
     * Relación con 'users' usando la clave foránea 'creado_id'.
     */
    public function creador(): BelongsTo
    {
        // Asegúrate que el modelo 'User' existe en App\Models
        return $this->belongsTo(User::class, 'creado_id');
    }

    /**
     * Obtiene el usuario asignado a gestionar el lead.
     * Relación con 'users' usando la clave foránea 'asignado_id'.
     */
    public function asignado(): BelongsTo
    {
        // Asegúrate que el modelo 'User' existe en App\Models
        return $this->belongsTo(User::class, 'asignado_id');
    }

    /**
     * Obtiene el motivo de descarte (si aplica).
     * Relación con 'motivos_descarte' a través de 'motivo_descarte_id'.
     */
    public function motivoDescarte(): BelongsTo
    {
        return $this->belongsTo(MotivoDescarte::class);
    }

    /**
     * Obtiene el cliente asociado (si se ha convertido).
     * Relación con 'clientes' a través de 'cliente_id'.
     */
    public function cliente(): BelongsTo
    {
        // Asegúrate que el modelo 'Cliente' existe en App\Models
        return $this->belongsTo(Cliente::class);
    }

   

    // --- FIN RELACIONES ---

    // Si tienes comentarios polimórficos, añade aquí la relación morphMany:
    // use Illuminate\Database\Eloquent\Relations\MorphMany;
    // public function comentarios(): MorphMany
    // {
    //     return $this->morphMany(Comentario::class, 'comentable'); // Ajusta 'Comentario' a tu modelo
    // }

    public function comentarios(): MorphMany
{
    return $this->morphMany(Comentario::class, 'comentable')->latest();
}

  // Relación uno-a-muchos con Ventas (las ventas originadas por este lead)
  public function ventas(): HasMany
  {
      return $this->hasMany(Venta::class);
  }



}