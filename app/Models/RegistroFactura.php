<?php

namespace App\Models;

use App\Enums\EstadoRegistroFactura;
use App\Enums\MedioDePago;
use App\Enums\TipoRegistroFactura;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistroFactura extends Model
{
    use HasFactory;

    protected $table = 'registro_facturas';

    protected $fillable = [
        'cliente_id',
        'tipo',
        'estado',
        'motivo_rechazo',
        'fecha_expedicion',
        'fecha_operacion',
        'numero_factura',
        'base_imponible',
        'cuota_iva',
        'total_iva',
        'tipo_retencion',
        'retencion_irpf',
        'total_factura',
        'medio',
        'justificante_path',
        'ejercicio',
        'trimestre',
        'observaciones',
        'proveedor_id',
        'cliente_final_id',
    ];

    protected $casts = [
        'fecha_expedicion' => 'date',
        'fecha_operacion' => 'date',
        'base_imponible' => 'decimal:2',
        'cuota_iva' => 'decimal:2',
        'total_iva' => 'decimal:2',
        'tipo_retencion' => 'decimal:2',
        'retencion_irpf' => 'decimal:2',
        'total_factura' => 'decimal:2',
        'tipo' => TipoRegistroFactura::class,
        'medio' => MedioDePago::class,
        'estado' => EstadoRegistroFactura::class,
    ];

    // Relación con el cliente dueño de la factura (cliente de AsesorFy)
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    // Relación con el proveedor (en facturas de gasto)
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    // Relación con el cliente final (en facturas de ingreso)
    public function clienteFinal(): BelongsTo
    {
        return $this->belongsTo(ClienteFinal::class);
    }

    // Líneas de detalle de la factura
    public function lineas(): HasMany
    {
        return $this->hasMany(RegistroFacturaLinea::class);
    }

    // Lógica automática para calcular ejercicio y trimestre
    protected static function booted(): void
    {
        static::creating(function (self $registroFactura) {
            if ($registroFactura->fecha_expedicion) {
                $registroFactura->ejercicio = $registroFactura->fecha_expedicion->year;
                $registroFactura->trimestre = $registroFactura->fecha_expedicion->quarter . 'T';
            }
        });

        static::updating(function (self $registroFactura) {
            if ($registroFactura->isDirty('fecha_expedicion')) {
                $registroFactura->ejercicio = $registroFactura->fecha_expedicion->year;
                $registroFactura->trimestre = $registroFactura->fecha_expedicion->quarter . 'T';
            }
        });
    }
}
